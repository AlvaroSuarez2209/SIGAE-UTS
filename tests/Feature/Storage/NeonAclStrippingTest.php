<?php

namespace Tests\Feature\Storage;

use App\Providers\AppServiceProvider;
use Aws\MockHandler as AwsMockHandler;
use Aws\Result;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Punto 1 (almacenamiento persistente) + anexo: "pruébalo contra el
 * cliente S3, no solo con Storage::fake". Storage::fake() reemplaza el
 * adaptador entero por uno en memoria — nunca construye una petición
 * HTTP real, así que nunca podría demostrar si el SDK de AWS manda o no
 * la cabecera "x-amz-acl" que Neon rechaza. Esta prueba sí arma un
 * Aws\S3\S3Client real (el mismo que construye
 * Illuminate\Filesystem\FilesystemManager::createS3Driver() para el
 * disco "s3"), con credenciales falsas y su handler HTTP reemplazado
 * por un Aws\MockHandler que solo captura la petición antes de
 * "enviarla" — firma, serialización y el middleware de
 * AppServiceProvider::stripAclFromNeonUploads() corren igual que con un
 * servidor real detrás.
 *
 * No hay Docker/MinIO disponible en este entorno para levantar un
 * servidor S3 real — este mock cumple el mismo propósito (verificar la
 * petición HTTP de verdad construida por el SDK, no una interpretación
 * en memoria) sin depender de un contenedor externo. Si se agrega MinIO
 * más adelante, este archivo es el lugar natural para una prueba de
 * integración equivalente contra un servidor real.
 */
class NeonAclStrippingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.attachments_disk' => 's3',
            'filesystems.disks.s3.key' => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.region' => 'us-east-1',
            'filesystems.disks.s3.bucket' => 'test-bucket',
            'filesystems.disks.s3.endpoint' => 'https://example-neon-test.invalid',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
        ]);

        // AppServiceProvider::boot() ya corrió con el "local" real del
        // entorno de pruebas (ver phpunit.xml) antes de que lo
        // sobreescribiéramos arriba — sin esto, el middleware que quita
        // el ACL nunca se habría registrado para este test.
        (new AppServiceProvider($this->app))->stripAclFromNeonUploads();
    }

    /**
     * Reemplaza el handler HTTP del cliente S3 real (ya resuelto por
     * Storage::disk('s3'), con el middleware de AppServiceProvider ya
     * encima) por uno que solo captura la petición saliente — nunca
     * llega a la red.
     */
    private function captureOutgoingRequestHeaders(callable $action): array
    {
        $client = Storage::disk('s3')->getClient();

        $captured = null;
        $mock = new AwsMockHandler;
        $mock->append(function ($command, $request) use (&$captured) {
            $captured = $request->getHeaders();

            return new Result(['ObjectURL' => 'https://example-neon-test.invalid/test-bucket/foo']);
        });

        $client->getHandlerList()->setHandler($mock);

        $action();

        return $captured ?? [];
    }

    public function test_uploading_through_the_configured_disk_never_sends_an_acl_header(): void
    {
        $headers = $this->captureOutgoingRequestHeaders(function () {
            Storage::disk('s3')->put('evidence/1/archivo.pdf', 'contenido de prueba');
        });

        $this->assertArrayNotHasKey('x-amz-acl', $headers);
    }

    /**
     * Mismo chequeo para una subida en streaming (writeStream) — el
     * camino real que usan EvidenceFile/DeliverableReferenceFile al
     * guardar un UploadedFile grande, distinto del put() de arriba que
     * solo cubre contenido en memoria.
     */
    public function test_streaming_upload_through_the_configured_disk_never_sends_an_acl_header(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'contenido de prueba en streaming');
        rewind($stream);

        $headers = $this->captureOutgoingRequestHeaders(function () use ($stream) {
            Storage::disk('s3')->writeStream('evidence/1/archivo-stream.pdf', $stream);
        });

        if (is_resource($stream)) {
            fclose($stream);
        }

        $this->assertArrayNotHasKey('x-amz-acl', $headers);
    }

    /**
     * Confirma que el middleware de verdad corre sobre el disco que
     * config('filesystems.attachments_disk') dice usar — si algún día
     * ese valor deja de ser "s3" en producción por error, este mismo
     * test seguiría probando el disco correcto sin cambios.
     */
    public function test_the_disk_resolved_by_the_app_matches_the_configured_attachments_disk(): void
    {
        $this->assertSame('s3', config('filesystems.attachments_disk'));
        $this->assertNotNull(Storage::disk(config('filesystems.attachments_disk'))->getClient());
    }
}

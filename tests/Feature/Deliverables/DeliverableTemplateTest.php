<?php

namespace Tests\Feature\Deliverables;

use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Deliverables\TemplateForm;
use App\Livewire\Deliverables\TemplateIndex;
use App\Models\DeliverableTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliverableTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    /**
     * Corrección tras pruebas manuales al punto 1: los inactivos
     * siempre aparecen en la lista; el filtro de 3 estados solo acota
     * la lista que ya los muestra a todos por defecto.
     */
    public function test_template_index_always_shows_inactive_records_and_status_filter_narrows_the_list(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        DeliverableTemplate::factory()->create(['name' => 'Plantilla activa']);
        DeliverableTemplate::factory()->create(['name' => 'Plantilla inactiva', 'is_active' => false]);

        Livewire::actingAs($coordination)
            ->test(TemplateIndex::class)
            ->assertSee('Plantilla activa')
            ->assertSee('Plantilla inactiva')
            ->set('statusFilter', 'active')
            ->assertSee('Plantilla activa')
            ->assertDontSee('Plantilla inactiva');
    }

    public function test_coordination_can_create_a_template(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(TemplateForm::class)
            ->set('name', 'Informe trimestral')
            ->set('periodicity_type', 'by_term')
            ->set('allowed_evidence_types', [EvidenceType::File->value])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('deliverable-templates.index', ['search' => '', 'statusFilter' => '']));

        $this->assertDatabaseHas('deliverable_templates', ['name' => 'Informe trimestral']);

        // Bloque de ajustes de interfaz, punto 5: este flash se perdía —
        // redirigía a deliverable-templates.index, que no tenía ningún
        // bloque que lo mostrara. Confirma con una petición real (no
        // Livewire::test(), que no renderiza el layout) que ahora sí llega.
        $this->actingAs($coordination)->get(route('deliverable-templates.index'))->assertSee('Plantilla guardada correctamente.');
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: el botón "Guardar" no tenía
     * ningún estado de carga — mismo patrón ya usado en
     * Login/InstitutionSettingsForm/TeacherImportWizard.
     */
    public function test_the_save_button_disables_itself_and_shows_a_loading_state(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(TemplateForm::class)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="save"', false)
            ->assertSee('Guardando...');
    }

    /**
     * Prioridad 4: antes de este cambio, TemplateForm no validaba nombres
     * duplicados en absoluto (ni en BD ni en formulario) — a diferencia de
     * los 5 catálogos bajo "Catálogos", que ya lo tenían resuelto. Mismo
     * criterio que los demás: insensible a mayúsculas/tildes, alcance
     * global (no hay un "padre" del que dependa una plantilla).
     */
    public function test_template_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        DeliverableTemplate::factory()->create(['name' => 'Informe Trimestral']);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class)
            ->set('name', 'INFORME TRIMESTRAL')
            ->set('periodicity_type', 'single')
            ->set('allowed_evidence_types', [EvidenceType::File->value])
            ->call('save')
            ->assertHasErrors('name');

        $this->assertDatabaseCount('deliverable_templates', 1);
    }

    public function test_editing_a_template_does_not_flag_its_own_unchanged_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create(['name' => 'Informe Trimestral']);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template])
            ->set('is_mandatory', false)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_template_requires_at_least_one_evidence_type(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class)
            ->set('name', 'Sin tipos de evidencia')
            ->set('periodicity_type', 'single')
            ->set('allowed_evidence_types', [])
            ->call('save')
            ->assertHasErrors('allowed_evidence_types');

        $this->assertDatabaseCount('deliverable_templates', 0);
    }

    public function test_deactivating_a_template_preserves_it(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(TemplateIndex::class)
            ->call('toggleActive', $template);

        $this->assertDatabaseHas('deliverable_templates', ['id' => $template->id, 'is_active' => false]);
    }

    public function test_editing_a_template_prefills_its_plain_array_fields_without_crashing(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create([
            'allowed_evidence_types' => [EvidenceType::File->value, EvidenceType::Link->value],
            'allowed_file_types' => ['pdf', 'zip'],
        ]);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template])
            ->assertSet('allowed_evidence_types', [EvidenceType::File->value, EvidenceType::Link->value])
            ->assertSet('allowed_file_types', ['pdf', 'zip']);
    }

    // --- Punto 5: enlace y archivo de referencia ---

    public function test_creating_a_template_with_a_reference_link_and_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $coordination = $this->userWithRole(RoleName::Coordination);
        $file = \Illuminate\Http\UploadedFile::fake()->create('referencia.pdf', 500, 'application/pdf');

        Livewire::actingAs($coordination)
            ->test(TemplateForm::class)
            ->set('name', 'Informe trimestral')
            ->set('periodicity_type', 'by_term')
            ->set('allowed_evidence_types', [EvidenceType::File->value])
            ->set('reference_url', 'https://uts.edu.co/referencia.pdf')
            ->set('newReferenceFiles', [$file])
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors();

        $template = DeliverableTemplate::where('name', 'Informe trimestral')->first();

        $this->assertNotNull($template);
        $this->assertSame('https://uts.edu.co/referencia.pdf', $template->reference_url);

        $referenceFile = $template->referenceFiles()->first();
        $this->assertNotNull($referenceFile);
        $this->assertSame('referencia.pdf', $referenceFile->original_name);
        $this->assertTrue($referenceFile->is_active);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($referenceFile->disk_path);
    }

    /**
     * "rechaza javascript:, ftp:, texto libre" — probado contra los 3
     * sobre entregable Y plantilla, ya que ambos usan App\Rules\HttpUrl.
     */
    public function test_a_reference_url_with_a_disallowed_scheme_or_free_text_is_rejected(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        foreach (['javascript:alert(1)', 'ftp://files.example.com/x.pdf', 'no es una url'] as $badUrl) {
            Livewire::actingAs($coordination)
                ->test(TemplateForm::class)
                ->set('name', 'Informe '.$badUrl)
                ->set('periodicity_type', 'by_term')
                ->set('allowed_evidence_types', [EvidenceType::File->value])
                ->set('reference_url', $badUrl)
                ->call('save')
                ->assertHasErrors('reference_url');
        }
    }

    public function test_a_valid_https_reference_url_is_accepted(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(TemplateForm::class)
            ->set('name', 'Informe con enlace válido')
            ->set('periodicity_type', 'by_term')
            ->set('allowed_evidence_types', [EvidenceType::File->value])
            ->set('reference_url', 'https://uts.edu.co/referencia.pdf')
            ->call('save')
            ->assertHasNoErrors('reference_url');
    }

    public function test_the_upload_field_is_replaced_by_the_saved_file_and_a_quitar_button_once_one_exists(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create();
        $file = $template->attachReferenceFile(
            \Illuminate\Http\UploadedFile::fake()->create('formato.pdf', 100, 'application/pdf'),
            $admin
        );

        $html = Livewire::actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template])
            ->html();

        $this->assertStringContainsString('formato.pdf', $html);
        $this->assertStringContainsString('removeReferenceFile('.$file->id.')', $html);
        $this->assertStringNotContainsString('Arrastra el archivo aquí', $html);
    }

    public function test_removing_the_templates_reference_file_is_audited_and_never_deletes_the_row_or_the_physical_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create();
        $file = $template->attachReferenceFile(
            \Illuminate\Http\UploadedFile::fake()->create('formato.pdf', 100, 'application/pdf'),
            $admin
        );

        Livewire::actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template])
            ->call('removeReferenceFile', $file);

        $file->refresh();
        $this->assertFalse($file->is_active);
        $this->assertDatabaseHas('deliverable_reference_files', ['id' => $file->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($file->disk_path);

        $log = \App\Models\AuditLog::where('action', 'deliverable_reference_file_removed')
            ->where('auditable_type', DeliverableTemplate::class)
            ->where('auditable_id', $template->id)
            ->first();
        $this->assertNotNull($log);
    }
}

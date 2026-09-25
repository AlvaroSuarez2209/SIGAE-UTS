<?php

namespace Tests\Unit\Lang;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * lang/es/validation.php le faltaban las traducciones de 'attributes' para
 * la mayoría de los campos del sistema — sin ellas, Laravel muestra el
 * nombre técnico de la columna tal cual ("assigned_hours") dentro del
 * mensaje ya traducido, en vez de su nombre en español natural. Cada caso
 * aquí reproduce una regla real de un módulo distinto, para que agregar un
 * campo nuevo sin su entrada en 'attributes' rompa un test, no se note
 * solo en producción.
 */
class ValidationAttributesTest extends TestCase
{
    public function test_assignment_hours_validation_message_is_in_natural_spanish(): void
    {
        $validator = Validator::make(
            ['assigned_hours' => 100],
            ['assigned_hours' => 'numeric|max:60']
        );

        $this->assertSame(
            'El campo horas asignadas no debe ser mayor que 60.',
            $validator->errors()->first('assigned_hours')
        );
    }

    public function test_deliverable_max_file_size_validation_message_is_in_natural_spanish(): void
    {
        $validator = Validator::make(
            ['max_file_size_mb' => 500],
            ['max_file_size_mb' => 'integer|max:100']
        );

        $this->assertSame(
            'El campo tamaño máximo de archivo (MB) no debe ser mayor que 100.',
            $validator->errors()->first('max_file_size_mb')
        );
    }

    public function test_leadership_starts_at_validation_message_is_in_natural_spanish(): void
    {
        $validator = Validator::make([], ['starts_at' => 'required|date']);

        $this->assertSame(
            'El campo vigente desde es obligatorio.',
            $validator->errors()->first('starts_at')
        );
    }

    public function test_user_document_number_validation_message_is_in_natural_spanish(): void
    {
        $validator = Validator::make([], ['document_number' => 'required']);

        $this->assertSame(
            'El campo número de documento es obligatorio.',
            $validator->errors()->first('document_number')
        );
    }

    public function test_evidence_exemption_justification_validation_message_is_in_natural_spanish(): void
    {
        $validator = Validator::make(
            ['exemptionJustification' => ''],
            ['exemptionJustification' => 'required']
        );

        $this->assertSame(
            'El campo justificación de la exención es obligatorio.',
            $validator->errors()->first('exemptionJustification')
        );
    }
}

<?php

namespace Tests\Unit\Components;

use Tests\TestCase;

/**
 * Rediseño visual de evidence-workspace.blade.php y review-show.blade.php,
 * punto 4/9: <x-formatted-text> solo convierte el texto en lista numerada
 * cuando TODAS las líneas no vacías son pasos consecutivos desde 1 — nunca
 * por encontrar "1." en cualquier parte del texto.
 */
class FormattedTextTest extends TestCase
{
    private function render(string $text): string
    {
        return view('components.formatted-text', ['text' => $text])->render();
    }

    public function test_two_or_more_numbered_steps_render_as_an_ordered_list(): void
    {
        $html = $this->render("1. Primer paso.\n2. Segundo paso.\n3. Tercer paso.");

        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('<li>Primer paso.</li>', $html);
        $this->assertStringContainsString('<li>Segundo paso.</li>', $html);
        $this->assertStringContainsString('<li>Tercer paso.</li>', $html);
    }

    public function test_a_single_numbered_line_is_not_enough_to_become_a_list(): void
    {
        $html = $this->render('1. Único punto, sin un segundo paso.');

        $this->assertStringNotContainsString('<ol', $html);
        $this->assertStringContainsString('1. Único punto, sin un segundo paso.', $html);
    }

    public function test_a_sentence_that_starts_with_a_year_is_never_confused_with_a_numbered_step(): void
    {
        $html = $this->render("Vigente 2026. Debe incluir información actualizada.\nSegunda línea de contexto.");

        $this->assertStringNotContainsString('<ol', $html);
        $this->assertStringContainsString('Vigente 2026. Debe incluir información actualizada.', $html);
    }

    public function test_a_reference_to_a_point_number_mid_sentence_is_never_confused_with_a_numbered_step(): void
    {
        $html = $this->render("Ver punto 1.5 del formato institucional.\nAdjuntar como PDF.");

        $this->assertStringNotContainsString('<ol', $html);
        $this->assertStringContainsString('Ver punto 1.5 del formato institucional.', $html);
    }

    public function test_steps_out_of_order_or_with_a_gap_are_not_treated_as_a_list(): void
    {
        $html = $this->render("1. Primer paso.\n3. Tercer paso (salta el 2).");

        $this->assertStringNotContainsString('<ol', $html);
    }

    public function test_each_step_is_escaped(): void
    {
        $html = $this->render("1. <script>alert('x')</script>\n2. Segundo paso.");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_plain_text_without_newlines_preserves_line_breaks_display(): void
    {
        $html = $this->render("Primera línea.\nSegunda línea.");

        $this->assertStringContainsString('whitespace-pre-line', $html);
        $this->assertStringContainsString("Primera línea.\nSegunda línea.", $html);
    }

    /**
     * Ajustes al rediseño visual, punto 8: un criterio de cumplimiento
     * real, escrito como un párrafo corrido (sin pulsar Enter entre cada
     * paso), no se convertía en lista — la versión anterior solo
     * comparaba línea por línea, y un párrafo sin saltos de línea nunca
     * llega a tener 2 "líneas". El texto exacto que reportó la directora
     * usaba "…" para acortar cada paso al pegarlo; aquí se reconstruyen
     * los 5 pasos completos (los puntos 3 y 4 no se mostraron en el
     * reporte) conservando la misma forma: todo en un solo párrafo, sin
     * saltos de línea entre pasos.
     */
    public function test_a_real_run_on_paragraph_with_five_inline_numbered_steps_renders_as_an_ordered_list(): void
    {
        $html = $this->render(
            '1. La actividad corresponde a lo planificado en la distribución docente. '
            .'2. Se describe claramente qué se entregó y en qué contexto. '
            .'3. La evidencia está fechada dentro del periodo académico correspondiente. '
            .'4. El formato del archivo coincide con lo permitido para este entregable. '
            .'5. El documento es presentado en su versión final, sin cambios pendientes.'
        );

        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('<li>La actividad corresponde a lo planificado en la distribución docente.</li>', $html);
        $this->assertStringContainsString('<li>El documento es presentado en su versión final, sin cambios pendientes.</li>', $html);
    }
}

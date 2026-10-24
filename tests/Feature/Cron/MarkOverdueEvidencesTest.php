<?php

namespace Tests\Feature\Cron;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Evidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Mismo patrón de pruebas que Tests\Feature\Cron\ProcessQueueTest — mismo
 * secreto compartido (config('cron.secret')), mismo criterio de "falla
 * cerrado sin token".
 */
class MarkOverdueEvidencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_a_request_with_no_token(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $this->get('/cron/mark-overdue-evidences')->assertForbidden();
    }

    public function test_rejects_a_request_with_the_wrong_token(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $this->get('/cron/mark-overdue-evidences?token=incorrecto')->assertForbidden();
    }

    public function test_rejects_every_request_when_no_secret_is_configured(): void
    {
        Config::set('cron.secret', null);

        $this->get('/cron/mark-overdue-evidences?token=')->assertForbidden();
    }

    public function test_accepts_a_request_with_the_correct_token_without_any_authentication(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $this->get('/cron/mark-overdue-evidences?token=el-secreto')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_accepts_the_token_via_header_instead_of_the_query_string(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $this->withHeader('X-Cron-Secret', 'el-secreto')
            ->get('/cron/mark-overdue-evidences')
            ->assertOk();
    }

    public function test_actually_marks_an_overdue_evidence_as_expired(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'due_at' => now()->subDay(),
        ]);
        $evidence = Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'status' => EvidenceStatus::Pending,
        ]);

        $this->get('/cron/mark-overdue-evidences?token=el-secreto')->assertOk();

        $this->assertEquals(EvidenceStatus::Expired, $evidence->fresh()->status);
    }
}

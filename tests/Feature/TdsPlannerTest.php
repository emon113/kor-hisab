<?php

namespace Tests\Feature;

use App\Services\Tax\TdsPlanner;
use Tests\TestCase;

class TdsPlannerTest extends TestCase
{
    private function months(): array
    {
        $months = [];
        foreach (TdsPlanner::MONTHS as $i => $key) {
            $months[$key] = ['salary' => 100000, 'bonus' => 0, 'tds' => $i < 3 ? 3000 : 0, 'done' => $i < 3];
        }

        return $months;
    }

    public function test_planner_page_renders(): void
    {
        $this->get('/tds-planner')->assertOk()->assertSee('Monthly TDS planner')->assertSee('Message for HR');
        $this->withCookie('kh_locale', 'bn')->get('/tds-planner')->assertOk()->assertSee('মাসিক উৎসে কর (টিডিএস) পরিকল্পনা');
    }

    public function test_plan_endpoint(): void
    {
        $this->postJson('/tds-planner/plan', ['year' => '2027-28', 'category' => 'general', 'months' => $this->months()])
            ->assertOk()
            ->assertJsonPath('gross', 1200000)
            ->assertJsonPath('deducted', 9000)
            ->assertJsonPath('open_months', 9)
            ->assertJsonPath('months.3.label', 'October 2026')
            ->assertJsonStructure(['liability', 'remaining', 'status', 'hr_text', 'months' => [['key', 'salary', 'suggested', 'cumulative_needed']]]);
    }

    public function test_plan_is_in_bangla(): void
    {
        $this->withCredentials()->withCookie('kh_locale', 'bn')
            ->postJson('/tds-planner/plan', ['year' => '2027-28', 'category' => 'general', 'months' => $this->months()])
            ->assertOk()
            ->assertJsonPath('months.3.label', 'অক্টোবর ২০২৬');
    }

    public function test_plan_input_is_validated(): void
    {
        $months = $this->months();
        $months['oct']['salary'] = -1;

        $this->postJson('/tds-planner/plan', ['year' => '2027-28', 'category' => 'general', 'months' => $months])
            ->assertStatus(422)->assertJsonValidationErrors('months.oct.salary');
        $this->postJson('/tds-planner/plan', ['year' => '2027-28', 'category' => 'general', 'months' => ['jul' => []] + ['xyz' => []]])
            ->assertStatus(422)->assertJsonValidationErrors('months');
    }
}

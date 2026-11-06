<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use Illuminate\Foundation\Testing\TestCase;
use Livewire\Livewire;

class DashboardPreviewTest extends TestCase
{
    public function test_dashboard_renders_with_the_indonesian_default_and_uses_the_selected_locale(): void
    {
        $this->get('/')->assertOk()->assertSee('Aksi lapangan')->assertSee('Pratinjau lokal');

        $this->post('/preview/language', ['locale' => 'zh-CN'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('现场操作')->assertSee('lang="zh-CN"', false);

        $this->post('/preview/language', ['locale' => 'en'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Field actions')->assertSee('lang="en"', false);

        $this->from('/')->post('/preview/language', ['locale' => 'invalid'])
            ->assertRedirect('/')->assertSessionHasErrors('locale');
    }

    public function test_lookup_is_validated_and_limited_to_the_selected_sample_branch(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Jakarta')
            ->call('lookup')->assertHasErrors(['assetCode' => 'required'])
            ->set('assetCode', 'invalid')->call('lookup')->assertHasErrors(['assetCode' => 'regex'])
            ->set('assetCode', 'ast-000128')->call('lookup')
            ->assertHasNoErrors()->assertSet('matchedCode', 'AST-000128')
            ->set('branch', 'bdg')->assertSet('matchedCode', null)
            ->assertSee('Kantor Cabang')->assertDontSee('Gedung Utama')
            ->set('assetCode', 'AST-000128')->call('lookup')->assertHasErrors('assetCode')
            ->set('assetCode', 'AST-000127')->call('lookup')
            ->assertHasNoErrors()->assertSet('matchedCode', 'AST-000127');
    }
}

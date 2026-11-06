<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use Illuminate\Foundation\Testing\TestCase;
use Livewire\Livewire;

class DashboardPreviewTest extends TestCase
{
    public function test_guest_layout_and_preview_share_the_session_language(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/')->assertOk()->assertSee('Sistem Manajemen Aset')->assertSee('lang="id"', false);
        $this->get('/preview')->assertOk()->assertSee('Aksi lapangan')->assertSee('Pratinjau lokal');

        $this->post('/language', ['locale' => 'zh-CN'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('资产管理系统')->assertSee('lang="zh-CN"', false);
        $this->get('/preview')->assertOk()->assertSee('现场操作')->assertSee('lang="zh-CN"', false);

        $this->post('/language', ['locale' => 'en'])->assertRedirect('/');
        $this->get('/preview')->assertOk()->assertSee('Field actions')->assertSee('lang="en"', false);

        $this->from('/')->post('/language', ['locale' => 'invalid'])
            ->assertRedirect('/')->assertSessionHasErrors('locale');
        $this->get('/')->assertOk()->assertSee('Choose Indonesian, English, or Simplified Chinese.');
    }

    public function test_preview_remains_inaccessible_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->get('/')->assertOk()->assertDontSee('Buka pratinjau dashboard');
        $this->get('/preview')->assertNotFound();
    }

    public function test_live_language_switch_preserves_branch_draft_and_lookup_result(): void
    {
        Livewire::test(Dashboard::class)
            ->set('branch', 'bdg')
            ->set('assetCode', 'AST-000127')->call('lookup')
            ->set('assetCode', 'AST-0001')
            ->call('changeLocale', 'en')
            ->assertDispatched('ams-locale-changed', locale: 'en', title: 'Dashboard · AMS')
            ->assertSee('Field actions')->assertSee('Asset found')
            ->assertSet('branch', 'bdg')
            ->assertSet('assetCode', 'AST-0001')
            ->assertSet('searchedCode', 'AST-000127')
            ->call('changeLocale', 'invalid')->assertHasErrors('locale');
    }

    public function test_visible_lookup_validation_uses_the_current_language(): void
    {
        Livewire::test(Dashboard::class)
            ->call('lookup')->assertSee('Masukkan kode aset dari label.')
            ->call('changeLocale', 'en')->assertSee('Enter the asset code from its label.')
            ->assertDontSee('Masukkan kode aset dari label.');
    }

    public function test_lookup_is_validated_and_limited_to_the_selected_sample_branch(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Jakarta')
            ->call('lookup')->assertHasErrors(['assetCode' => 'required'])
            ->set('assetCode', 'invalid')->call('lookup')->assertHasErrors(['assetCode' => 'regex'])
            ->set('assetCode', '  ast-000128  ')->call('lookup')
            ->assertHasNoErrors()->assertSet('searchedCode', 'AST-000128')
            ->set('branch', 'bdg')->assertSet('searchedCode', null)->assertSet('assetCode', 'AST-000128')
            ->assertSee('Kantor Cabang')->assertDontSee('Gedung Utama')
            ->set('assetCode', 'AST-000128')->call('lookup')
            ->assertSee(__('dashboard.lookup_missing'))->assertHasNoErrors()
            ->set('assetCode', 'AST-000127')->call('lookup')
            ->assertHasNoErrors()->assertSet('searchedCode', 'AST-000127');
    }
}

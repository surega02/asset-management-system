<?php

namespace App\Livewire;

use App\Support\Locale;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Dashboard extends Component
{
    private const BRANCHES = [
        'jkt' => [
            'name' => 'Jakarta',
            'locations' => ['main' => 'Gedung Utama', 'warehouse' => 'Gudang Operasional', 'service' => 'Area Layanan'],
            'assets' => [
                ['AST-000128', 'Laptop Operasional', 'main', true, 'registered', '10:42'],
                ['AST-000074', 'Proyektor Ruang Rapat', 'main', true, 'moved', '09:18', 'warehouse'],
                ['AST-000051', 'Printer Label', 'warehouse', true, 'condition', '08:56'],
                ['AST-000019', 'Pemindai Barcode', 'service', false, 'deactivated', '08:20'],
                ['AST-000016', 'Monitor 24 inci', 'main', true],
                ['AST-000014', 'Komputer Layanan', 'service', true],
                ['AST-000011', 'Rak Penyimpanan', 'warehouse', true],
                ['AST-000008', 'Meja Pemeriksaan', 'warehouse', true],
                ['AST-000006', 'Kursi Kantor', 'main', true],
                ['AST-000004', 'Router Cadangan', 'main', true],
                ['AST-000003', 'Lemari Arsip', 'main', true],
                ['AST-000001', 'Timbangan Digital', 'warehouse', true],
            ],
        ],
        'bdg' => [
            'name' => 'Bandung',
            'locations' => ['office' => 'Kantor Cabang', 'warehouse' => 'Gudang Bandung', 'service' => 'Ruang Layanan'],
            'assets' => [
                ['AST-000127', 'Laptop Cabang', 'office', true, 'registered', '11:06'],
                ['AST-000103', 'Printer Multifungsi', 'service', true, 'moved', '09:24', 'office'],
                ['AST-000094', 'Monitor Cadangan', 'warehouse', false, 'deactivated', '08:31'],
                ['AST-000085', 'Proyektor Portabel', 'office', true],
                ['AST-000083', 'Pemindai Dokumen', 'service', true],
                ['AST-000061', 'Rak Peralatan', 'warehouse', true],
            ],
        ],
    ];

    public string $branch = 'jkt';

    public string $assetCode = '';

    #[Locked]
    public ?string $searchedCode = null;

    public function boot(): void
    {
        abort_unless(app()->environment('local', 'testing'), 404);
    }

    public function updatedBranch(): void
    {
        $this->reset('searchedCode');
        $this->resetValidation();
    }

    public function changeLocale(string $value): void
    {
        $locale = Locale::set($value);

        $this->dispatch('ams-locale-changed', locale: $locale, title: __('dashboard.dashboard').' · AMS');
    }

    public function lookup(): void
    {
        $this->reset('searchedCode');
        $this->resetValidation();
        $this->assetCode = strtoupper(trim($this->assetCode));
        $this->validate(
            ['assetCode' => ['required', 'regex:/\AAST-[0-9]{6}\z/']],
            ['assetCode.required' => 'dashboard.lookup_required', 'assetCode.regex' => 'dashboard.lookup_format'],
        );

        $this->searchedCode = $this->assetCode;
    }

    private function currentBranch(): array
    {
        abort_unless(isset(self::BRANCHES[$this->branch]), 422);

        return self::BRANCHES[$this->branch];
    }

    public function render(): View
    {
        $branch = $this->currentBranch();
        $assets = collect($branch['assets']);
        $active = $assets->where(3, true)->count();

        return view('livewire.dashboard', [
            'branchNames' => array_column(self::BRANCHES, 'name'),
            'branchKeys' => array_keys(self::BRANCHES),
            'branchName' => $branch['name'],
            'exampleCode' => $branch['assets'][0][0] ?? '',
            'locations' => $branch['locations'],
            'locationTotals' => $assets->countBy(2),
            'total' => $assets->count(),
            'active' => $active,
            'inactive' => $assets->count() - $active,
            'activity' => $assets->filter(fn (array $asset): bool => isset($asset[4])),
            'match' => $assets->firstWhere(0, $this->searchedCode),
        ])->layout('components.layouts.app');
    }
}

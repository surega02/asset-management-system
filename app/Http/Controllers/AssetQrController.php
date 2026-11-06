<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Queries\Assets;
use App\Support\AssetQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AssetQrController
{
    public const TOKEN_PATTERN = '[a-f0-9]{64}';

    public function scan(): Response
    {
        return response()->view('assets.scan', ['qrPrefix' => AssetQr::prefix()], headers: [
            'Permissions-Policy' => 'camera=(self), microphone=()',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function publicResult(string $token): Response
    {
        // The public query selects only the allowlist and the state needed to compute general status.
        $asset = DB::table('assets')->where('assets.public_token', $token)
            ->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->join('asset_categories', 'asset_categories.id', '=', 'asset_types.category_id')
            ->join('asset_statuses', 'asset_statuses.id', '=', 'assets.status_id')
            ->first(['assets.asset_code', 'assets.name', 'asset_categories.name as category', 'asset_statuses.name as status', 'assets.is_active']);
        abort_if($asset === null, 404);

        $fields = [
            'code' => $asset->asset_code, 'name' => $asset->name, 'category' => $asset->category,
            'status' => $asset->is_active ? $asset->status : __('assets.inactive'),
        ];

        return response()->view('assets.qr-result', compact('fields', 'token'), headers: [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function detail(string $token): RedirectResponse
    {
        $asset = Asset::query()->where('public_token', $token)->firstOrFail();
        Gate::authorize('view', $asset);

        return to_route('assets.show', $asset)->header('Referrer-Policy', 'no-referrer');
    }

    public function findByCode(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Asset::class);
        $code = $request->validate(['asset_code' => ['required', 'string', 'regex:/\AAST-[0-9]{6,19}\z/i']],
            attributes: ['asset_code' => __('qr.code')])['asset_code'];
        $asset = Assets::visible($request->user())->where('asset_code', strtoupper($code))->first();
        if ($asset === null) {
            return to_route('assets.scan')->withErrors(['asset_code' => __('qr.code_unavailable')])->withInput();
        }
        Gate::authorize('view', $asset);

        return to_route('assets.show', $asset);
    }
}

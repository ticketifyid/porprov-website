<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanLogController extends Controller
{
    private const PER_PAGE = 25;

    private const METHODS = ['camera', 'hardware', 'manual'];

    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('q', ''));
        $result = (string) $request->query('result', '');
        $result = ScanResult::tryFrom($result)?->value ?? '';
        $method = (string) $request->query('method', '');
        $method = in_array($method, self::METHODS, true) ? $method : '';

        $logs = ScanLog::query()
            ->with(['registration', 'user'])
            ->when($result !== '', fn ($query) => $query->where('result', $result))
            ->when($method !== '', fn ($query) => $query->where('method', $method))
            ->when($keyword !== '', function ($query) use ($keyword) {
                $escaped = '%'.addcslashes($keyword, '%_\\').'%';

                $query->where(function ($query) use ($escaped) {
                    $query->whereHas('registration', function ($query) use ($escaped) {
                        $query->where('name', 'like', $escaped)->orWhere('code', 'like', $escaped);
                    })->orWhereHas('user', function ($query) use ($escaped) {
                        $query->where('name', 'like', $escaped)->orWhere('username', 'like', $escaped);
                    });
                });
            })
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.scan-logs.index', [
            'logs' => $logs,
            'keyword' => $keyword,
            'result' => $result,
            'method' => $method,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Employees\ImportEmployees;
use App\Actions\Employees\ReadEmployeeSpreadsheet;
use App\Models\Employee;
use App\Models\Position;
use App\Queries\PeopleScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EmployeeImportController
{
    public function create(Request $request): View
    {
        Gate::authorize('import', Employee::class);
        $departments = PeopleScope::departments($request->user(), 'employees.manage')->where('is_active', true)->with('owner.owner')->orderBy('name')->get();
        $positions = Position::query()->where('is_active', true)->orderBy('name')->get();
        $locations = PeopleScope::homeLocations($request->user())->with('owner')->orderBy('name')->get();
        $batches = DB::table('employee_imports')->where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDays(90))->orderByDesc('id')->simplePaginate(10);

        return view('people.import-form', compact('departments', 'positions', 'locations', 'batches'));
    }

    public function template(): Response
    {
        Gate::authorize('import', Employee::class);

        return response(implode(',', ReadEmployeeSpreadsheet::COLUMNS)."\r\n", headers: [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="employee-import.csv"',
        ]);
    }

    public function store(Request $request, ImportEmployees $import): RedirectResponse
    {
        Gate::authorize('import', Employee::class);
        $file = $request->validate(['file' => ['required', 'file', 'max:10240']], ['file.max' => __('people.import_file_invalid')], ['file' => __('people.file')])['file'];
        $batch = $import->upload($request->user(), $file);

        return to_route('employees.import.show', $batch);
    }

    public function show(Request $request, int $batch): View
    {
        $record = $this->batch($request, $batch);
        $query = DB::table('employee_import_rows')->where('employee_import_id', $record->id);
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $canRetry = (clone $query)->where('status', 'failed')->whereNotNull('payload')->exists();
        $rows = $query->select('line', 'status', 'error_key', 'attempts')->orderBy('line')->simplePaginate(25);

        return view('people.import-result', compact('record', 'rows', 'counts', 'canRetry'));
    }

    public function process(Request $request, int $batch, ImportEmployees $import): RedirectResponse
    {
        $this->batch($request, $batch);
        $import->process($request->user(), $batch, $request->boolean('retry'));

        return to_route('employees.import.show', $batch)->with('status', __('people.import_processed'));
    }

    private function batch(Request $request, int $batch): object
    {
        Gate::authorize('import', Employee::class);
        $record = DB::table('employee_imports')->where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDays(90))->find($batch);
        abort_unless($record, 404);

        return $record;
    }
}

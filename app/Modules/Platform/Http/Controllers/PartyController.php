<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    public const ROLES = ['CUSTOMER', 'SUPPLIER', 'SUBCONTRACTOR', 'AGENT', 'FORWARDER'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Party::class);
        $company = app(CompanyContext::class)->requireCompany($request);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = $data['q'] ?? '';
        $parties = Party::where('company_id', $company->id)->with('roles')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('code', 'like', '%'.$search.'%')
                    ->orWhere('legal_name', 'like', '%'.$search.'%');
            }))->orderBy('legal_name')->paginate(25)->withQueryString();

        return view('platform.parties.index', compact('parties', 'company', 'search'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Party::class);

        return view('platform.parties.form', [
            'party' => new Party(['active' => true]),
            'company' => app(CompanyContext::class)->requireCompany($request),
            'roles' => self::ROLES,
            'selectedRoles' => [],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Party::class);
        $company = app(CompanyContext::class)->requireCompany($request);
        $data = $this->validated($request, $company->id);
        DB::transaction(function () use ($data, $company) {
            $roles = $data['roles'];
            unset($data['roles']);
            $party = Party::create($data + ['company_id' => $company->id]);
            $this->saveRoles($party, $roles);
            SystemLog::record('CREATE', 'Platform Party', "company_id={$company->id}; party_id={$party->id}");
        });

        return redirect()->route('platform.company.edit')->with('success', 'Party berhasil dibuat.');
    }

    public function edit(Request $request, int $id)
    {
        $company = app(CompanyContext::class)->requireCompany($request);
        $party = Party::where('company_id', $company->id)->findOrFail($id);
        $this->authorize('update', $party);

        return view('platform.parties.form', [
            'party' => $party, 'company' => $company, 'roles' => self::ROLES,
            'selectedRoles' => $party->roles()->where('active', true)->pluck('role')->all(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $company = app(CompanyContext::class)->requireCompany($request);
        $party = Party::where('company_id', $company->id)->findOrFail($id);
        $this->authorize('update', $party);
        $data = $this->validated($request, $company->id, $party);
        DB::transaction(function () use ($data, $party, $company) {
            // Serialise changes to the master and its role list together.
            $party = Party::where('company_id', $company->id)->lockForUpdate()->findOrFail($party->id);
            $roles = $data['roles'];
            unset($data['roles']);
            $party->fill($data);
            $changed = implode(',', array_keys($party->getDirty()));
            $party->save();
            $this->saveRoles($party, $roles);
            SystemLog::record('UPDATE', 'Platform Party', "company_id={$company->id}; party_id={$party->id}; fields={$changed},roles");
        });

        return redirect()->route('platform.company.edit')->with('success', 'Party berhasil diperbarui.');
    }

    private function validated(Request $request, int $companyId, ?Party $party = null): array
    {
        if (! $request->has('roles')) {
            $request->merge(['roles' => []]);
        }
        $unique = Rule::unique('parties', 'code')->where('company_id', $companyId);
        if ($party) {
            $unique->ignore($party->id);
        }

        $data = $request->validate([
            'company_id' => ['prohibited'],
            'code' => ['required', 'string', 'max:64', $unique],
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'active' => ['required', 'boolean'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', Rule::in(self::ROLES)],
        ]);
        unset($data['company_id']);

        return $data;
    }

    private function saveRoles(Party $party, array $roles): void
    {
        // Preserve old role rows for history instead of deleting them.
        $party->roles()->update(['active' => false]);
        foreach ($roles as $role) {
            $party->roles()->updateOrCreate(['role' => $role], ['active' => true]);
        }
    }
}

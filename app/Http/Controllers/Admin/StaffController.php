<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('manage-staff');

        return view('admin.staff.index', [
            'personnel' => User::personnel()->orderBy('name')->get(),
            'roles' => [UserRole::Staff, UserRole::Admin],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-staff');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in([UserRole::Staff->value, UserRole::Admin->value])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            // Hashed by the model's cast; the plain value never reaches the
            // database and is not written to the audit trail either.
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        $this->audit->record('staff.created', $user, $request->user(), after: [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ]);

        return back()->with('status', "{$user->name} can now sign in as {$user->role->label()}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage-staff');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in([UserRole::Staff->value, UserRole::Admin->value])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // An admin cannot change their own role -- the fastest way to lock a
        // property out of its own admin area.
        if ($validated['role'] !== $user->role->value) {
            $this->authorize('changeRole', $user);
        }

        $before = $user->only(['name', 'email', 'role']);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
        ]);

        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();

        $this->audit->record('staff.updated', $user, $request->user(), $before, $user->only(['name', 'email', 'role']));

        return back()->with('status', "{$user->name} updated.");
    }

    /**
     * Deactivate rather than delete, so this person's attribution on
     * historical payments and transitions keeps resolving.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->record(
            $user->is_active ? 'staff.reactivated' : 'staff.deactivated',
            $user,
            $request->user(),
        );

        return back()->with('status', $user->is_active
            ? "{$user->name} can sign in again."
            : "{$user->name} can no longer sign in. Their past actions remain on record.");
    }
}

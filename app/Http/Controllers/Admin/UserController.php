<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\Staff\StaffAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff accounts (spec section 8.2). Admin only: user.manage on each route.
 */
class UserController extends Controller
{
    public function __construct(private readonly StaffAccountService $accounts) {}

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'title' => __('users.new'),
            'action' => route('admin.users.store'),
            'method' => 'POST',
            'item' => null,
            'resetUrl' => null,
            'roles' => StaffRole::cases(),
        ]);
    }

    /**
     * The one-time password is shown on this page only. It is not stored or flashed to the session.
     */
    public function store(StoreUserRequest $request): View
    {
        [$user, $password] = $this->accounts->create($request->userData(), $this->actor($request));

        return view('admin.users.password', [
            'heading' => __('users.created_heading'),
            'user' => $user,
            'password' => $password,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'title' => __('users.edit'),
            'action' => route('admin.users.update', $user),
            'method' => 'PUT',
            'item' => $user,
            // An admin resets other accounts' passwords. Their own goes through the password change page.
            'resetUrl' => $user->is($this->actor(request())) ? null : route('admin.users.reset-password', $user),
            'roles' => StaffRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->accounts->update($user, $request->userData(), $this->actor($request));

        return redirect()->route('admin.users.index')->with('success', __('users.saved'));
    }

    public function resetPassword(Request $request, User $user): View|RedirectResponse
    {
        $actor = $this->actor($request);

        if ($user->is($actor)) {
            return redirect()->route('admin.users.edit', $user)->with('error', __('users.self_reset'));
        }

        $password = $this->accounts->resetPassword($user, $actor);

        return view('admin.users.password', [
            'heading' => __('users.reset_heading'),
            'user' => $user,
            'password' => $password,
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}

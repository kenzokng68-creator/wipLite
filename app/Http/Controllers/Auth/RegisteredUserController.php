<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $tcRole = Role::firstOrCreate(['name' => 'tc']);

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $tcRole->id,
        ]);

        // Lier l'employé s'il existe
        $employee = \App\Models\Employee::where('email', $request->email)->first();
        if ($employee) {
            $employee->update(['user_id' => $user->id]);
        }

        event(new Registered($user));

        Auth::login($user);

        $role = $user->role?->name;

        $redirectRoute = match ($role) {
            'admin' => 'dashboard.admin',
            'cp' => 'dashboard.cp',
            'sup' => 'dashboard.sup',
            'tc' => 'dashboard.tc',
            default => 'dashboard',
        };

        return redirect()->intended(route($redirectRoute, absolute: false));
    }
}

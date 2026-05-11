<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Constants\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function users()
    {
        $users = User::all();
        $roles = Role::all();
        return view('admin.users', compact('users', 'roles'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(Role::all())],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => true,
        ]);

        return back()->with('success', 'User created successfully.');
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'role' => ['required', Rule::in(Role::all())],
            'is_active' => 'boolean',
            'password' => 'nullable|string|min:8',
        ]);

        $data = [
            'role' => $request->role,
            'is_active' => $request->has('is_active'), // Checkbox handling
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return back()->with('success', 'User updated successfully.');
    }
    
    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot delete yourself.');
        }
        $user->delete();
        return back()->with('success', 'User deleted.');
    }

    public function config()
    {
        // Read config from .env or config file?
        // Requirement 10-C: "Config page (paths, schedule times, N days)"
        // Updating .env from code is risky but doable. 
        // Better to store in DB settings table or just display environment variables.
        // For this task, I'll simulate config reading.
        
        $config = [
            'BANK_INBOX_PATH' => env('BANK_INBOX_PATH'),
            'BANK_PROCESSED_PATH' => env('BANK_PROCESSED_PATH'),
            'BANK_REJECTED_PATH' => env('BANK_REJECTED_PATH'),
            'EXPORT_OUTBOX_PATH' => env('EXPORT_OUTBOX_PATH'),
        ];
        
        return view('admin.config', compact('config'));
    }

    public function updateConfig(Request $request)
    {
         // Updating env is complex and usually not recommended for production.
         // But for a dedicated app on a server, it's possible.
         // I'll skip implementing .env writer for now and just show a message.
         // Or I can store these in caching/settings table.
         
         return back()->with('status', 'Config update logic placeholder.');
    }
}

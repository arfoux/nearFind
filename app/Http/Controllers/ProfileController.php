<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $profile = $this->resolveProfile($request);

        $submittedNeeds = $profile->needs()
            ->withCount('supports')
            ->latest()
            ->take(30)
            ->get();

        $supportedNeeds = $profile->supportedNeeds()
            ->withCount('supports')
            ->latest('need_supports.created_at')
            ->take(30)
            ->get();

        $reports = $profile->reports()
            ->with('need:id,title,category,status')
            ->latest()
            ->take(30)
            ->get();

        return view('profile.index', [
            'profile' => $profile,
            'categories' => collect(config('needs.categories'))->keyBy('id'),
            'submittedNeeds' => $submittedNeeds,
            'supportedNeeds' => $supportedNeeds,
            'reports' => $reports,
            'stats' => [
                'submitted' => $submittedNeeds->count(),
                'supported' => $supportedNeeds->count(),
                'reports' => $reports->count(),
            ],
            'notificationSettings' => $profile->notificationSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $this->resolveProfile($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('profiles', 'username')->ignore($profile->id),
            ],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+()\-\s]+$/'],
            'bio' => ['nullable', 'string', 'max:300'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 100 karakter.',
            'username.max' => 'Username maksimal 30 karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'username.unique' => 'Username ini sudah dipakai warga lain.',
            'phone.max' => 'Nomor telepon terlalu panjang.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'bio.max' => 'Bio maksimal 300 karakter.',
            'avatar.image' => 'Foto profil harus berupa gambar.',
            'avatar.mimes' => 'Foto profil harus berformat JPG, PNG, atau WEBP.',
            'avatar.max' => 'Ukuran foto profil maksimal 2 MB.',
        ]);

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');

            if ($avatarPath) {
                $this->deleteAvatar($profile);
                $validated['avatar_path'] = $avatarPath;
            }
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($profile);
            $validated['avatar_path'] = null;
        }

        unset($validated['avatar'], $validated['remove_avatar']);

        $profile->update($validated);

        return redirect()
            ->route('profile.index')
            ->with('status', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $profile = $this->resolveProfile($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru belum cocok.',
        ]);

        $currentHash = $profile->password_hash ?: (string) config('seal.auth.password_hash');

        if ($currentHash === '' || ! Hash::check($validated['current_password'], $currentHash)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak cocok.',
            ]);
        }

        $profile->update([
            'password_hash' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('profile.index')
            ->with('status', 'Kata sandi berhasil diperbarui.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $profile = $this->resolveProfile($request);

        $profile->update([
            'notification_preferences' => [
                'need_updates' => $request->boolean('need_updates'),
                'support_activity' => $request->boolean('support_activity'),
                'weekly_digest' => $request->boolean('weekly_digest'),
            ],
        ]);

        return redirect()
            ->route('profile.index')
            ->with('status', 'Pengaturan notifikasi disimpan.');
    }

    private function resolveProfile(Request $request): Profile
    {
        $user = $request->user();

        $profile = Profile::findByEmail($user->email);

        if ($profile) {
            return $profile;
        }

        return Profile::create([
            'email' => $user->email,
            'name' => $user->name,
            'username' => $this->uniqueUsername($user->email),
            'notification_preferences' => [
                'need_updates' => true,
                'support_activity' => true,
                'weekly_digest' => false,
            ],
        ]);
    }

    private function uniqueUsername(string $email): string
    {
        $base = Profile::usernameFromEmail($email);
        $username = $base;
        $suffix = 1;

        while (Profile::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = $base.$suffix;
        }

        return $username;
    }

    private function deleteAvatar(Profile $profile): void
    {
        if ($profile->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\Need;
use App\Models\NeedSupport;
use App\Models\Profile;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $profile = Profile::where('email', $request->user()->email)->first() ?? $this->resolveProfile($request);

        // The compiled rung has no relation objects: read the tables directly.
        // `supports_count` and the pivot date are attached as plain attributes,
        // which is what the view reads either way.
        $submittedNeeds = Need::where('profile_id', $profile->id)->latest()->limit(30)->get();

        foreach ($submittedNeeds as $need) {
            $need->supports_count = NeedSupport::where('need_id', $need->id)->count();
        }

        $supports = NeedSupport::where('profile_id', $profile->id)
            ->latest('created_at')
            ->limit(30)
            ->get();

        $supportedNeeds = [];

        foreach ($supports as $support) {
            $need = Need::find($support->need_id);

            if ($need === null) {
                continue;
            }

            $need->supports_count = NeedSupport::where('need_id', $need->id)->count();
            $need->pivot = (object) ['created_at' => $support->created_at];
            $supportedNeeds[] = $need;
        }

        $reportRows = Report::where('profile_id', $profile->id)->latest()->limit(30)->get();
        $reports = [];

        foreach ($reportRows as $report) {
            $need = $report->need_id !== null ? Need::find($report->need_id) : null;

            // A plain row: the view must not reach through `$report->need`.
            $reports[] = [
                'category' => $report->category,
                'need_category' => $need !== null ? $need->category : null,
                'title' => $report->need_title,
                'need_title' => $need !== null ? $need->title : null,
                'status' => $report->status,
                'reason' => $report->reason,
                'created_at' => $report->created_at,
            ];
        }

        // A plain id => category map (not a Collection): the view reads it with
        // an array index, which compiles for the edge (a Collection method call
        // inside a view is refused).
        $categories = [];

        foreach (config('needs.categories') as $category) {
            $categories[(string) $category['id']] = $category;
        }

        return view('profile.index', [
            'profile' => $profile,
            'categories' => $categories,
            'submittedNeeds' => $submittedNeeds,
            'supportedNeeds' => $supportedNeeds,
            'reports' => $reports,
            'stats' => [
                'submitted' => count($submittedNeeds),
                'supported' => count($supportedNeeds),
                'reports' => count($reports),
            ],
            'notificationSettings' => $profile->notification_settings,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $profile = Profile::where('email', $request->user()->email)->first() ?? $this->resolveProfile($request);

        $validated = $request->validated();

        // The uniqueness rule lives here, not in the FormRequest: the edge
        // evaluates `rules()` at build time, so `Rule::unique()->ignore(...)`
        // would carry a NULL id and reject the row itself. The same message and
        // the same 302-with-errors the rule answered.
        if (($validated['username'] ?? null) !== null) {
            $taken = Profile::where('username', $validated['username'])->first();

            if ($taken !== null && $taken->id !== $profile->id) {
                throw ValidationException::withMessages([
                    'username' => 'Username ini sudah dipakai.',
                ]);
            }
        }

        // The avatar feature is gone for now (a default one is derived from the
        // row id), so the write is plain attributes + save().
        $profile->name = $validated['name'];
        $profile->username = $validated['username'] ?? null;
        $profile->phone = $validated['phone'] ?? null;
        $profile->bio = $validated['bio'] ?? null;
        $profile->save();

        return redirect()
            ->route('profile.index')
            ->with('status', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $profile = Profile::where('email', $request->user()->email)->first() ?? $this->resolveProfile($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru belum cocok.',
        ]);

        // The configured credential is a DEPLOYMENT secret, not public config: the
        // compiled rung reads it from a binding (`env()`), never from the config
        // dump that ships in dist/.
        $currentHash = $profile->password_hash ?: (string) env('NEARFIND_AUTH_PASSWORD_HASH');

        if ($currentHash === '' || ! Hash::check($validated['current_password'], $currentHash)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak cocok.',
            ]);
        }

        $profile->password_hash = Hash::make($validated['password']);
        $profile->save();

        return redirect()
            ->route('profile.index')
            ->with('status', 'Kata sandi berhasil diperbarui.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $profile = Profile::where('email', $request->user()->email)->first() ?? $this->resolveProfile($request);

        $profile->notification_preferences = [
            'need_updates' => $request->boolean('need_updates'),
            'support_activity' => $request->boolean('support_activity'),
            'weekly_digest' => $request->boolean('weekly_digest'),
        ];
        $profile->save();

        return redirect()
            ->route('profile.index')
            ->with('status', 'Pengaturan notifikasi disimpan.');
    }

    private function resolveProfile(Request $request): Profile
    {
        $user = $request->user();

        $profile = Profile::where('email', $user->email)->first();

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

    /**
     * A unique handle for a profile created lazily at the edge. Same shape as
     * `Profile::usernameFromEmail()`, plus a numeric suffix while the base is
     * taken; written with an inline `where()` (the compiled surface) so the
     * controller never calls a static helper the transpiler would refuse.
     */
    private function uniqueUsername(string $email): string
    {
        $raw = strtolower(Str::before($email, '@'));
        $base = '';

        for ($i = 0; $i < strlen($raw); $i++) {
            $ch = substr($raw, $i, 1);

            if (($ch >= 'a' && $ch <= 'z') || ($ch >= '0' && $ch <= '9') || $ch === '.' || $ch === '_' || $ch === '-') {
                $base .= $ch;
            }
        }

        $base = trim($base, '._-');

        if ($base === '') {
            $base = 'pengguna';
        }

        $base = substr($base, 0, 24);

        $username = $base;
        $suffix = 1;

        while (Profile::where('username', $username)->first() !== null) {
            $suffix++;
            $username = $base.$suffix;
        }

        return $username;
    }
}

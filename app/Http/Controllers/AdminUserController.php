<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\User;
use App\Support\PersonPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who may use the AI assistant, and who is allowed in at all.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $me = $request->user()->id;

        $users = User::query()
            ->withCount(['people', 'categories'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'ai_approved' => $user->canUseAi(),
                'verified' => $user->email_verified_at !== null,
                'joined' => $user->created_at?->format('j M Y'),
                'people_count' => $user->people_count,
                'categories_count' => $user->categories_count,
                'is_self' => $user->id === $me,
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
        ]);
    }

    public function updateAiApproval(Request $request, User $user): RedirectResponse
    {
        $approved = $request->boolean('approved');

        // Revoking your own access would lock you out of the assistant you just
        // approved for everyone else.
        if (! $approved && $user->id === $request->user()->id) {
            return back()->with('error', 'You cannot switch off your own AI access.');
        }

        // Not mass assignable, by design.
        $user->forceFill(['ai_approved_at' => $approved ? now() : null])->save();

        return back()->with('status', $approved
            ? 'AI switched on for '.$user->name.'.'
            : 'AI switched off for '.$user->name.'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account here.');
        }

        if ($user->is_admin) {
            return back()->with('error', 'That account is an admin.');
        }

        $name = $user->name;

        // The rows cascade, but the uploaded photos are files on disk and have
        // to be cleared out by hand.
        $user->people()->get()->each(
            fn (Person $person) => PersonPhotos::forget($person->photo_path),
        );

        $user->delete();

        return back()->with('status', $name.' and their rolodex were deleted.');
    }
}

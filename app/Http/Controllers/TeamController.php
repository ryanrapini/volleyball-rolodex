<?php

namespace App\Http\Controllers;

use App\Enums\TeamResponse;
use App\Http\Requests\TeamRequest;
use App\Models\Category;
use App\Models\Person;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\CategoryFilters;
use App\Support\PersonPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    use AuthorizesRequests;

    /**
     * The teams saved so far, soonest tournament first.
     */
    public function index(Request $request): Response
    {
        $teams = $request->user()->teams()
            ->withCount('members')
            ->orderByRaw('tournament_date is null')
            ->orderBy('tournament_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'date' => $team->tournament_date?->toDateString(),
                'date_label' => $team->tournament_date?->format('D j M Y'),
                'members_count' => $team->members_count,
                'tally' => $team->tally(),
            ])
            ->all();

        return Inertia::render('Teams/Index', ['teams' => $teams]);
    }

    /**
     * Step one: what is being built, and what it is being built for.
     */
    public function build(Request $request): Response
    {
        $questions = $this->questions($request->user());

        return Inertia::render('Teams/Build', [
            'questions' => $questions,
            'ready' => $questions !== [],
        ]);
    }

    /**
     * Step two: everyone who matches, in a random order to work through.
     */
    public function deck(Request $request): Response
    {
        $user = $request->user();
        $categories = $this->builderCategories($user);

        // Answered questions only: a question left alone is not a filter.
        $answers = CategoryFilters::read($request->query('a'), $categories, useDefaults: false);

        $date = $request->query('date');
        $date = is_string($date) && $date !== '' ? $date : null;

        $people = $user->people()
            ->when($answers !== [], fn (Builder $query) => CategoryFilters::apply($query, $answers))
            // Somebody who has already said no to this tournament is not asked
            // again, which is what "a no sticks for that date" means in practice.
            ->when($date !== null, fn (Builder $query) => $query->whereNotIn(
                'id',
                $this->declined($user, $date),
            ))
            ->with(['categoryValues.category', 'categoryValues.option'])
            ->inRandomOrder()
            ->limit(200)
            ->get()
            ->map(fn (Person $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'photo_url' => PersonPhotos::url($person->photo_path),
                'tags' => $person->categoryTags(8),
            ])
            ->all();

        return Inertia::render('Teams/Deck', [
            'questions' => $this->questions($user),
            'answers' => array_map(fn (array $values): array => array_values($values), $answers),
            'name' => (string) $request->query('name', ''),
            'date' => $date,
            'eligible' => $people,
        ]);
    }

    /**
     * Save the people that were kept.
     */
    public function store(TeamRequest $request): RedirectResponse
    {
        $team = $request->user()->teams()->create([
            'name' => (string) $request->string('name'),
            'tournament_date' => $request->input('tournament_date') ?: null,
        ]);

        $ids = $request->personIds();

        foreach ($ids as $position => $personId) {
            $team->members()->create([
                'person_id' => $personId,
                'position' => $position,
            ]);
        }

        return redirect()
            ->route('teams.show', $team)
            ->with('status', $team->name.' has '.count($ids).' to ask.');
    }

    public function show(Team $team): Response
    {
        $this->authorize('view', $team);

        $members = $team->members()
            ->with(['person.categoryValues.category', 'person.categoryValues.option'])
            ->get()
            ->map(fn (TeamMember $member): array => [
                'person_id' => $member->person_id,
                'name' => $member->person?->name ?? 'Someone removed',
                'phone' => $member->person?->phone,
                'email' => $member->person?->email,
                'photo_url' => PersonPhotos::url($member->person?->photo_path),
                'tags' => $member->person?->categoryTags(8) ?? [],
                'response' => $member->response->value,
                'response_label' => $member->response->short(),
            ])
            ->all();

        return Inertia::render('Teams/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'date' => $team->tournament_date?->toDateString(),
                'date_label' => $team->tournament_date?->format('D j M Y'),
            ],
            'members' => $members,
            'tally' => $team->tally(),
        ]);
    }

    /**
     * What they said when they were asked. Answered as JSON so a list of people
     * can be worked through without the page reloading under the thumb.
     */
    public function respond(Request $request, Team $team, Person $person): JsonResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'response' => ['required', Rule::enum(TeamResponse::class)],
        ]);

        $member = $team->members()->where('person_id', $person->getKey())->first();

        if ($member === null) {
            return response()->json(['error' => 'They are not on this team.'], 404);
        }

        $member->update(['response' => $validated['response']]);

        return response()->json([
            'person_id' => $member->person_id,
            'response' => $member->response->value,
            'response_label' => $member->response->short(),
            'tally' => $team->tally(),
        ]);
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $name = $team->name;
        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', 'Removed '.$name.'.');
    }

    /**
     * The categories worth asking about when a team is being built.
     *
     * @return Collection<int, Category>
     */
    private function builderCategories(User $user): Collection
    {
        return $user->categories()
            ->with('options')
            ->where('in_team_builder', true)
            ->orderBy('position')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questions(User $user): array
    {
        return $this->builderCategories($user)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'chips' => CategoryFilters::chips($category),
            ])
            ->values()
            ->all();
    }

    /**
     * Everyone who said no to a team on this date.
     *
     * @return array<int, string>
     */
    private function declined(User $user, string $date): array
    {
        return TeamMember::query()
            ->where('response', TeamResponse::No->value)
            ->whereHas('team', fn (Builder $query) => $query
                ->where('user_id', $user->getKey())
                ->whereDate('tournament_date', $date))
            ->pluck('person_id')
            ->all();
    }
}

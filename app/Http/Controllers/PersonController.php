<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonRequest;
use App\Models\Person;
use App\Support\PersonPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    use AuthorizesRequests;

    /**
     * Home screen for the whole app: search the rolodex and scroll the list.
     */
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));

        $people = $request->user()->people()
            ->when($term !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $this->applySearch($search, $term),
            ))
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString()
            ->through(fn (Person $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'photo_url' => PersonPhotos::url($person->photo_path),
                'notes_excerpt' => Str::limit((string) $person->notes, 120),
            ]);

        return Inertia::render('People/Index', [
            'people' => $people,
            'filters' => ['q' => $term],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('People/Create');
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        $person = $request->user()->people()->create(
            $request->safe()->except(['photo', 'remove_photo']),
        );

        if ($request->hasFile('photo')) {
            PersonPhotos::store($person, $request->file('photo'));
        }

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Added '.$person->name.' to your rolodex.');
    }

    public function show(Person $person): Response
    {
        $this->authorize('view', $person);

        return Inertia::render('People/Show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'notes' => $person->notes,
                'photo_url' => PersonPhotos::url($person->photo_path),
            ],
        ]);
    }

    public function edit(Person $person): Response
    {
        $this->authorize('update', $person);

        return Inertia::render('People/Edit', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'notes' => $person->notes,
                'photo_url' => PersonPhotos::url($person->photo_path),
            ],
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $person->update($request->safe()->except(['photo', 'remove_photo']));

        if ($request->hasFile('photo')) {
            PersonPhotos::store($person, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            PersonPhotos::forget($person->photo_path);
            $person->photo_path = null;
            $person->save();
        }

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Saved '.$person->name.'.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $this->authorize('delete', $person);

        $name = $person->name;
        $person->delete();

        return redirect()
            ->route('people.index')
            ->with('status', 'Removed '.$name.'.');
    }

    /**
     * Match the term against every text field on the record.
     *
     * The term is escaped rather than stripped, so a literal "%" or "_" in the
     * box matches that character instead of turning into a wildcard. SQLite and
     * PostgreSQL both honour the ESCAPE clause used here.
     *
     * @param  Builder<Person>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term));
        $needle = '%'.$escaped.'%';

        $query->whereRaw("lower(name) like ? escape '\\'", [$needle]);

        foreach (['phone', 'email', 'notes'] as $column) {
            $query->orWhereRaw("lower(".$column.") like ? escape '\\'", [$needle]);
        }

        // Dialling digits should find a number however it was punctuated.
        $digits = preg_replace('/\D+/', '', $term);

        if (is_string($digits) && $digits !== '') {
            $query->orWhereRaw('phone_digits like ?', ['%'.$digits.'%']);
        }
    }
}

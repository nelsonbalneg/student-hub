<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegalDocumentRequest;
use App\Http\Requests\UpdateLegalDocumentRequest;
use App\Models\LegalDocument;
use App\Services\LegalDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LegalDocumentController extends Controller
{
    public function __construct(private readonly LegalDocumentService $legalDocuments) {}

    public function index(Request $request): Response
    {
        $request->user()->can('legal.view') || abort(403);

        $documents = LegalDocument::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->withCount('acceptances')
            ->when($request->query('type'), fn ($query, string $type) => $query->where('type', $type))
            ->when($request->query('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Legal/Index', [
            'documents' => [
                'data' => collect($documents->items())->map(fn (LegalDocument $document): array => [
                    ...$this->legalDocuments->serialize($document, includeContent: false),
                    'acceptances_count' => $document->acceptances_count,
                    'creator' => $document->creator?->only(['id', 'name']),
                    'updater' => $document->updater?->only(['id', 'name']),
                ])->values(),
                'links' => $documents->linkCollection(),
                'meta' => [
                    'current_page' => $documents->currentPage(),
                    'from' => $documents->firstItem(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'to' => $documents->lastItem(),
                    'total' => $documents->total(),
                ],
            ],
            'filters' => $request->only(['type', 'status']),
            'types' => $this->typeOptions(),
            'can' => $this->permissions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $request->user()->can('legal.create') || abort(403);

        return Inertia::render('Legal/Create', [
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(StoreLegalDocumentRequest $request): RedirectResponse
    {
        $validated = $this->payload($request->validated());

        DB::transaction(function () use ($request, $validated): void {
            if ($validated['is_active']) {
                LegalDocument::query()
                    ->where('type', $validated['type'])
                    ->update(['is_active' => false]);
            }

            LegalDocument::query()->create([
                ...$validated,
                'published_at' => $validated['is_active'] ? now() : null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('legal.index')->with('success', 'Legal document created.');
    }

    public function show(Request $request, LegalDocument $legalDocument): Response
    {
        $request->user()->can('legal.view') || abort(403);

        $legalDocument->load(['creator:id,name', 'updater:id,name'])->loadCount('acceptances');

        return Inertia::render('Legal/Show', [
            'document' => [
                ...$this->legalDocuments->serialize($legalDocument),
                'acceptances_count' => $legalDocument->acceptances_count,
                'creator' => $legalDocument->creator?->only(['id', 'name']),
                'updater' => $legalDocument->updater?->only(['id', 'name']),
            ],
            'can' => $this->permissions($request),
        ]);
    }

    public function edit(Request $request, LegalDocument $legalDocument): Response
    {
        $request->user()->can('legal.edit') || abort(403);

        return Inertia::render('Legal/Edit', [
            'document' => $this->legalDocuments->serialize($legalDocument),
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateLegalDocumentRequest $request, LegalDocument $legalDocument): RedirectResponse
    {
        $validated = $this->payload($request->validated(), $legalDocument);

        DB::transaction(function () use ($request, $legalDocument, $validated): void {
            if ($validated['is_active']) {
                LegalDocument::query()
                    ->where('type', $validated['type'])
                    ->whereKeyNot($legalDocument->id)
                    ->update(['is_active' => false]);
            }

            $legalDocument->update([
                ...$validated,
                'published_at' => $validated['is_active']
                    ? ($legalDocument->published_at ?? now())
                    : null,
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('legal.show', $legalDocument)->with('success', 'Legal document updated.');
    }

    public function destroy(Request $request, LegalDocument $legalDocument): RedirectResponse
    {
        $request->user()->can('legal.delete') || abort(403);

        $legalDocument->delete();

        return redirect()->route('legal.index')->with('success', 'Legal document deleted.');
    }

    public function activate(Request $request, LegalDocument $legalDocument): RedirectResponse
    {
        $request->user()->can('legal.activate') || abort(403);

        DB::transaction(function () use ($request, $legalDocument): void {
            LegalDocument::query()
                ->where('type', $legalDocument->type)
                ->whereKeyNot($legalDocument->id)
                ->update(['is_active' => false]);

            $legalDocument->update([
                'is_active' => true,
                'published_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Legal document activated.');
    }

    public function deactivate(Request $request, LegalDocument $legalDocument): RedirectResponse
    {
        $request->user()->can('legal.activate') || abort(403);

        $legalDocument->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Legal document deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $validated, ?LegalDocument $document = null): array
    {
        $title = $validated['title'];

        return [
            'type' => $validated['type'],
            'title' => $title,
            'slug' => ($validated['slug'] ?? '') ?: $this->uniqueSlug($title, $document),
            'content' => $validated['content'],
            'version' => $validated['version'] ?: null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    private function uniqueSlug(string $title, ?LegalDocument $document = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (
            LegalDocument::query()
                ->where('slug', $slug)
                ->when($document, fn ($query) => $query->whereKeyNot($document->id))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return [
            ['value' => LegalDocument::TYPE_TERMS, 'label' => 'Terms and Conditions'],
            ['value' => LegalDocument::TYPE_COOKIE_POLICY, 'label' => 'Cookie Policy'],
            ['value' => LegalDocument::TYPE_PRIVACY_POLICY, 'label' => 'Privacy Policy'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(Request $request): array
    {
        return [
            'view' => $request->user()->can('legal.view'),
            'create' => $request->user()->can('legal.create'),
            'edit' => $request->user()->can('legal.edit'),
            'delete' => $request->user()->can('legal.delete'),
            'activate' => $request->user()->can('legal.activate'),
        ];
    }
}

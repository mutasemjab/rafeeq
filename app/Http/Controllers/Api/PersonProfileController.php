<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonProfileResource;
use App\Models\ChatAttachment;
use App\Models\PersonProfile;
use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PersonProfileController extends Controller
{
    public function index(Request $request)
    {
        return PersonProfileResource::collection($request->user()->personProfiles()->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge($this->profileRules(), [
            'relationship' => ['required', Rule::in(['self', 'caregiver'])],
            'has_permission' => 'required|accepted',
            'has_persistence_consent' => 'required|accepted',
            'has_ai_consent' => 'required|boolean',
            'consent_version' => 'required|string|max:32',
            'legacy_child_id' => ['nullable', 'integer', Rule::exists('children', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at'),
                Rule::unique('person_profiles')->where('user_id', $request->user()->id)],
        ]));
        abort_if(isset($data['legacy_child_id']) && $data['relationship'] !== 'caregiver', 422, 'A child link requires a caregiver relationship.');
        unset($data['has_permission'], $data['has_persistence_consent'], $data['has_ai_consent']);
        $person = $request->user()->personProfiles()->create(array_merge($data, [
            'permission_attested_at' => now(), 'persistence_consent_accepted_at' => now(),
            'ai_consent_accepted_at' => $request->boolean('has_ai_consent') ? now() : null,
        ]));

        return response()->json(new PersonProfileResource($person), 201);
    }

    public function show(Request $request, PersonProfile $personProfile)
    {
        $this->assertOwner($request, $personProfile);

        return response()->json(new PersonProfileResource($personProfile));
    }

    public function update(Request $request, PersonProfile $personProfile)
    {
        $this->assertOwner($request, $personProfile);
        $data = $request->validate($this->profileRules());
        DB::transaction(function () use ($personProfile, $data): void {
            $beforeAge = $personProfile->effectiveAgeMonths();
            $beforeBirth = $personProfile->birth_date?->format('Y-m-d');
            $personProfile->fill($data);
            $ageChanged = $beforeAge !== $personProfile->effectiveAgeMonths() || $beforeBirth !== $personProfile->birth_date?->format('Y-m-d');
            if ($ageChanged) { $personProfile->age_updated_at = now(); }
            $personProfile->save();
            if ($ageChanged) { app(\App\Services\AI\ProfileFactCorrectionService::class)->ageChanged($personProfile); }
        });

        return response()->json(new PersonProfileResource($personProfile->fresh()));
    }

    public function consent(Request $request, PersonProfile $personProfile)
    {
        $this->assertOwner($request, $personProfile);
        $data = $request->validate([
            'has_ai_consent' => 'required|boolean',
            'has_permission' => $request->boolean('has_ai_consent') ? 'required|accepted' : 'sometimes|accepted',
            'consent_version' => 'required_if:has_ai_consent,true|nullable|string|max:32',
        ]);
        $personProfile->update([
            'ai_consent_accepted_at' => $request->boolean('has_ai_consent') ? now() : null,
            'permission_attested_at' => $request->boolean('has_ai_consent') ? now() : $personProfile->permission_attested_at,
            'consent_version' => $data['consent_version'] ?? $personProfile->consent_version,
        ]);
        if ($request->boolean('has_ai_consent') && $request->user()->hasAiConsent()) {
            $personProfile->documents()->where('status', 'uploaded')->limit(20)->pluck('id')->each(function ($id): void {
                app(\App\Services\Documents\PrivateFileProcessingDispatcher::class)->dispatch(\App\Jobs\ProcessPersonDocumentJob::class, (int) $id);
            });
        }

        return response()->json(new PersonProfileResource($personProfile->fresh()));
    }

    public function memories(Request $request, PersonProfile $personProfile)
    {
        $this->assertOwner($request, $personProfile);

        return response()->json($personProfile->memories()->where('user_id', $request->user()->id)
            ->where('status', 'active')->latest('id')->paginate(20));
    }

    public function destroy(Request $request, PersonProfile $personProfile)
    {
        $this->assertOwner($request, $personProfile);
        DB::transaction(function () use ($personProfile): void {
            $person = PersonProfile::whereKey($personProfile->id)->lockForUpdate()->firstOrFail();
            $ids = $person->conversations()->withoutGlobalScope('unexpired')->withTrashed()->pluck('id');
            app(\App\Services\AI\ChatUsageService::class)->retainDeletedUsage((int) $person->user_id, $ids->all());
            foreach ($person->documents()->withTrashed()->lockForUpdate()->get() as $file) {
                app(PrivateDocumentStorage::class)->delete($file);
                $file->forceDelete();
            }
            foreach (ChatAttachment::withTrashed()->whereIn('conversation_id', $ids)->where('user_id', $person->user_id)->lockForUpdate()->get() as $file) {
                app(PrivateDocumentStorage::class)->delete($file);
                $file->chunks()->delete();
                $file->forceDelete();
            }
            $person->conversations()->withoutGlobalScope('unexpired')->withTrashed()->forceDelete();
            $person->forceDelete();
        });

        return response()->json(['message' => 'Person profile and associated conversations deleted.']);
    }

    private function assertOwner(Request $request, PersonProfile $person): void
    {
        abort_unless((int) $person->user_id === (int) $request->user()->id, 403);
    }

    private function profileRules(): array
    {
        return [
            'display_name' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'age_months' => 'nullable|integer|min:0|max:1560',
            'preferred_language' => ['sometimes', Rule::in(['ar', 'en'])],
            'communication_preferences' => 'nullable|string|max:1000',
            'reported_diagnosis' => 'nullable|string|max:1000',
            'diagnosis_source' => ['nullable', Rule::in(['self_report', 'caregiver_report', 'professional_report'])],
        ];
    }
}

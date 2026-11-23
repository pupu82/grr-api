<?php

namespace App\Exports;

use App\Actions\Grr\ParticipantAttributeAgreement;
use App\Actions\Grr\PartTypeAttributeEvaluation;
use App\Actions\Grr\SessionAgreementResult;
use App\Models\InspectionSession;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

/**
 * Flat, CSV-safe representation of the evaluation endpoint.
 *
 * The original session and participant rows keep their columns. Part-type rows
 * and their participant rows are appended, so a client that ignores the new
 * record types and columns still reads the session total.
 */
class SessionEvaluationExport implements FromCollection, WithCustomValueBinder, WithHeadings, WithTitle
{
    /**
     * @param  Collection<int, ParticipantAttributeAgreement>  $evaluations
     * @param  Collection<int, PartTypeAttributeEvaluation>  $partTypeEvaluations
     */
    public function __construct(
        private readonly InspectionSession $session,
        private readonly Collection $evaluations,
        private readonly SessionAgreementResult $agreement,
        private readonly Collection $partTypeEvaluations,
        private readonly SessionEvaluationValueBinder $valueBinder = new SessionEvaluationValueBinder,
    ) {}

    public function bindValue(Cell $cell, mixed $value): bool
    {
        return $this->valueBinder->bindValue($cell, $value);
    }

    public function collection(): Collection
    {
        $rows = collect([array_merge($this->baseRow('session'), $this->agreementFields($this->agreement))]);

        foreach ($this->evaluations as $evaluation) {
            $rows->push(array_merge($this->baseRow('participant'), $this->participantFields($evaluation)));
        }

        foreach ($this->partTypeEvaluations as $partType) {
            $partTypeFields = $this->partTypeFields($partType);

            $rows->push(
                array_merge(
                    $this->baseRow('part_type'),
                    $this->agreementFields($partType->agreement),
                    $partTypeFields,
                ),
            );

            foreach ($partType->evaluations as $evaluation) {
                $rows->push(
                    array_merge(
                        $this->baseRow('part_type_participant'),
                        $this->participantFields($evaluation),
                        $partTypeFields,
                    ),
                );
            }
        }

        return $rows->values();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'record_type',
            'session_id',
            'session_token',
            'session_date',
            'participant_id',
            'employee_code',
            'name',
            'effectiveness',
            'miss_rate',
            'false_alarm_rate',
            'decisions',
            'correct_decisions',
            'defect_decisions',
            'good_decisions',
            'missed_defects',
            'false_alarms',
            'timed_out_samples',
            'participants_compared',
            'compared_samples',
            'excluded_samples',
            'between_agreement',
            'all_vs_standard',
            'judgement',
            'part_type_id',
            'part_type_name',
            'is_primary',
            'samples_count',
        ];
    }

    public function title(): string
    {
        return 'Evaluation';
    }

    /**
     * @return array<string, mixed>
     */
    private function baseRow(string $recordType): array
    {
        return [
            'record_type' => $recordType,
            'session_id' => $this->session->uuid,
            'session_token' => $this->session->token,
            'session_date' => $this->session->session_date?->toDateString(),
            'participant_id' => null,
            'employee_code' => null,
            'name' => null,
            'effectiveness' => null,
            'miss_rate' => null,
            'false_alarm_rate' => null,
            'decisions' => null,
            'correct_decisions' => null,
            'defect_decisions' => null,
            'good_decisions' => null,
            'missed_defects' => null,
            'false_alarms' => null,
            'timed_out_samples' => null,
            'participants_compared' => null,
            'compared_samples' => null,
            'excluded_samples' => null,
            'between_agreement' => null,
            'all_vs_standard' => null,
            'judgement' => null,
            'part_type_id' => null,
            'part_type_name' => null,
            'is_primary' => null,
            'samples_count' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function agreementFields(SessionAgreementResult $agreement): array
    {
        return [
            'participants_compared' => $agreement->participantsCompared,
            'compared_samples' => $agreement->comparedSamples,
            'excluded_samples' => $agreement->excludedSamples,
            'between_agreement' => $this->decimal($agreement->betweenAgreement),
            'all_vs_standard' => $this->decimal($agreement->allVsStandard),
            'judgement' => $agreement->judgement?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function participantFields(ParticipantAttributeAgreement $evaluation): array
    {
        return [
            'participant_id' => $evaluation->participant->id,
            'employee_code' => $evaluation->participant->user?->employee_code,
            'name' => $evaluation->participant->user?->name,
            'effectiveness' => $this->decimal($evaluation->metrics->effectiveness),
            'miss_rate' => $this->decimal($evaluation->metrics->missRate),
            'false_alarm_rate' => $this->decimal($evaluation->metrics->falseAlarmRate),
            'decisions' => $evaluation->metrics->decisions,
            'correct_decisions' => $evaluation->metrics->correctDecisions,
            'defect_decisions' => $evaluation->metrics->defectDecisions,
            'good_decisions' => $evaluation->metrics->goodDecisions,
            'missed_defects' => $evaluation->metrics->missedDefects,
            'false_alarms' => $evaluation->metrics->falseAlarms,
            'timed_out_samples' => $evaluation->metrics->timedOutSamples,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partTypeFields(PartTypeAttributeEvaluation $partType): array
    {
        return [
            'part_type_id' => $partType->partType->id,
            'part_type_name' => $partType->partTypeName,
            'is_primary' => $partType->isPrimary ? 'true' : 'false',
            'samples_count' => $partType->samplesCount,
        ];
    }

    /**
     * Serialize a percentage with two decimals. `null` stays null so CSV and
     * XLSX both represent missing data as an empty cell, never as zero.
     */
    private function decimal(?float $value): ?string
    {
        return $value === null ? null : number_format($value, 2, '.', '');
    }
}

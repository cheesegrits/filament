<?php

namespace Filament\Tables\Actions;

use Closure;
use Exception;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class AttachAction extends Action
{
    use CanCustomizeProcess;

    protected ?Closure $modifyRecordSelectUsing = null;

    protected ?Closure $modifyRecordSelectOptionsQueryUsing = null;

    protected bool | Closure $canAttachAnother = true;

    protected bool | Closure $isRecordSelectPreloaded = false;

    protected string | Closure | null $recordTitleAttribute = null;

    /**
     * @var array<string> | Closure | null
     */
    protected array | Closure | null $recordSelectSearchColumns = null;

    public static function getDefaultName(): ?string
    {
        return 'attach';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-actions::attach.single.label'));

        $this->modalHeading(fn (): string => __('filament-actions::attach.single.modal.heading', ['label' => $this->getModelLabel()]));

        $this->modalButton(__('filament-actions::attach.single.modal.actions.attach.label'));

        $this->modalWidth('lg');

        $this->extraModalActions(function (): array {
            return $this->canAttachAnother() ? [
                $this->makeExtraModalAction('attachAnother', ['another' => true])
                    ->label(__('filament-actions::attach.single.modal.actions.attach_another.label')),
            ] : [];
        });

        $this->successNotificationTitle(__('filament-actions::attach.single.messages.attached'));

        $this->color('gray');

        $this->button();

        $this->form(fn (): array => [$this->getRecordSelect()]);

        $this->action(function (array $arguments, Form $form): void {
            $this->process(function (array $data, Table $table) {
                /** @var BelongsToMany $relationship */
                $relationship = $table->getRelationship();

                $record = $relationship->getRelated()->query()->find($data['recordId']);

                $relationship->attach(
                    $record,
                    Arr::only($data, $relationship->getPivotColumns()),
                );
            });

            if ($arguments['another'] ?? false) {
                $this->callAfter();
                $this->sendSuccessNotification();

                $form->fill();

                $this->halt();

                return;
            }

            $this->success();
        });
    }

    public function recordSelect(?Closure $callback): static
    {
        $this->modifyRecordSelectUsing = $callback;

        return $this;
    }

    public function recordSelectOptionsQuery(?Closure $callback): static
    {
        $this->modifyRecordSelectOptionsQueryUsing = $callback;

        return $this;
    }

    public function recordTitleAttribute(string | Closure | null $attribute): static
    {
        $this->recordTitleAttribute = $attribute;

        return $this;
    }

    public function attachAnother(bool | Closure $condition = true): static
    {
        $this->canAttachAnother = $condition;

        return $this;
    }

    /**
     * @deprecated Use `attachAnother()` instead.
     */
    public function disableAttachAnother(bool | Closure $condition = true): static
    {
        $this->attachAnother(fn (AttachAction $action): bool => ! $action->evaluate($condition));

        return $this;
    }

    public function preloadRecordSelect(bool | Closure $condition = true): static
    {
        $this->isRecordSelectPreloaded = $condition;

        return $this;
    }

    public function canAttachAnother(): bool
    {
        return (bool) $this->evaluate($this->canAttachAnother);
    }

    public function isRecordSelectPreloaded(): bool
    {
        return (bool) $this->evaluate($this->isRecordSelectPreloaded);
    }

    public function getRecordTitleAttribute(): string
    {
        $attribute = $this->evaluate($this->recordTitleAttribute);

        if (blank($attribute)) {
            throw new Exception('Attach table action must have a `recordTitleAttribute()` defined, which is used to identify records to attach.');
        }

        return $attribute;
    }

    /**
     * @param  array<string> | Closure | null  $columns
     */
    public function recordSelectSearchColumns(array | Closure | null $columns): static
    {
        $this->recordSelectSearchColumns = $columns;

        return $this;
    }

    /**
     * @return array<string> | null
     */
    public function getRecordSelectSearchColumns(): ?array
    {
        return $this->evaluate($this->recordSelectSearchColumns);
    }

    public function getRecordSelect(): Select
    {
        $table = $this->getTable();

        $getOptions = function (?string $search = null, ?array $searchColumns = []) use ($table): array {
            /** @var BelongsToMany $relationship */
            $relationship = $table->getRelationship();

            $titleAttribute = $this->getRecordTitleAttribute();

            $relationshipQuery = $relationship->getRelated()->query()->orderBy($titleAttribute);

            if ($this->modifyRecordSelectOptionsQueryUsing) {
                $relationshipQuery = $this->evaluate($this->modifyRecordSelectOptionsQueryUsing, [
                    'query' => $relationshipQuery,
                ]) ?? $relationshipQuery;
            }

            if (filled($search)) {
                $search = strtolower($search);

                /** @var Connection $databaseConnection */
                $databaseConnection = $relationshipQuery->getConnection();

                $searchOperator = match ($databaseConnection->getDriverName()) {
                    'pgsql' => 'ilike',
                    default => 'like',
                };

                $searchColumns ??= [$titleAttribute];
                $isFirst = true;

                $relationshipQuery->where(function (Builder $query) use ($isFirst, $searchColumns, $searchOperator, $search): Builder {
                    foreach ($searchColumns as $searchColumn) {
                        $whereClause = $isFirst ? 'where' : 'orWhere';

                        $query->{"{$whereClause}Raw"}(
                            "lower({$searchColumn}) {$searchOperator} ?",
                            "%{$search}%",
                        );

                        $isFirst = false;
                    }

                    return $query;
                });
            }

            $relatedKeyName = $relationship->getRelatedKeyName();

            return $relationshipQuery
                ->when(
                    ! $table->allowsDuplicates(),
                    fn (Builder $query): Builder => $query->whereDoesntHave(
                        $table->getInverseRelationship(),
                        function (Builder $query) use ($table): Builder {
                            return $query->where($table->getRelationship()->getParent()->getQualifiedKeyName(), $table->getRelationship()->getParent()->getKey());
                        },
                    ),
                )
                ->get()
                ->mapWithKeys(fn (Model $record): array => [$record->{$relatedKeyName} => $this->getRecordTitle($record)])
                ->toArray();
        };

        $select = Select::make('recordId')
            ->label(__('filament-actions::attach.single.modal.fields.record_id.label'))
            ->required()
            ->searchable($this->getRecordSelectSearchColumns() ?? true)
            ->getSearchResultsUsing(static fn (Select $component, string $search): array => $getOptions(search: $search, searchColumns: $component->getSearchColumns()))
            ->getOptionLabelUsing(fn ($value): string => $this->getRecordTitle($table->getRelationship()->getRelated()->query()->find($value)))
            ->options(fn (): array => $this->isRecordSelectPreloaded() ? $getOptions() : [])
            ->hiddenLabel();

        if ($this->modifyRecordSelectUsing) {
            $select = $this->evaluate($this->modifyRecordSelectUsing, [
                'select' => $select,
            ]);
        }

        return $select;
    }
}

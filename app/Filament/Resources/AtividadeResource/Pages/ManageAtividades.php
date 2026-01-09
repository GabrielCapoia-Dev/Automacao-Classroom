<?php

namespace App\Filament\Resources\AtividadeResource\Pages;

use App\Filament\Resources\AtividadeResource;
use App\Models\Atividade;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;

class ManageAtividades extends ManageRecords
{
    protected static string $resource = AtividadeResource::class;

    protected $turmasDaSerie = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova Atividade')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->slideOver()  // Abre como slideOver lateral
                ->modalWidth('4xl')  // Largura do modal
                ->modalHeading('Criar Nova Atividade')
                ->modalDescription('Preencha os dados para criar uma nova atividade')
                ->modalSubmitAction(false)
                ->createAnother(false)
                ->successNotificationTitle('Atividade criada com sucesso!')
                ->closeModalByClickingAway(false)
                ->after(function () {
                    // Código que roda após criar
                    \Filament\Notifications\Notification::make()
                        ->title('Sucesso!')
                        ->body('A atividade foi criada.')
                        ->success()
                        ->send();
                }),
        ];
    }
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $serieId  = $data['serie_id'];
        $escolas  = $data['escolas'] ?? [];

        // Turmas da série filtradas pelas escolas
        $turmas = \App\Models\Turma::query()
            ->where('serie_id', $serieId)
            ->whereIn('escola_id', $escolas)
            ->with(['professores', 'escola'])
            ->get();

        // Guarda temporariamente para usar depois
        $this->turmasDaSerie = $turmas;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\Atividade $atividade */
        $atividade = $this->record;

        foreach ($this->turmasDaSerie as $turma) {

            // Professores vinculados à turma (já filtrados por escola)
            $professores = $turma->professores
                ->where('escola_id', $turma->escola_id);

            foreach ($professores as $professor) {

                /**
                 * AQUI você decide o que fazer:
                 *
                 * - enviar atividade pro Classroom
                 * - registrar vínculo
                 * - criar log
                 * - criar pivot
                 */

                // Exemplo de vínculo lógico (ajuste ao seu modelo)
                $atividade->turmas()->attach($turma->id, [
                    'professor_id' => $professor->id,
                ]);
            }
        }
    }
}

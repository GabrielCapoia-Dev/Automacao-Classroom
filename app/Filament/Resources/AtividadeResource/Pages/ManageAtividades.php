<?php

namespace App\Filament\Resources\AtividadeResource\Pages;

use App\Filament\Resources\AtividadeResource;
use App\Models\Atividade;
use App\Services\Atividade\AtividadeSyncService;
use App\Services\GoogleMainService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageAtividades extends ManageRecords
{
    protected static string $resource = AtividadeResource::class;

    protected $turmasDaSerie = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importarAtividades')
                ->label('Importar do Classroom')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Importar Atividades do Classroom')
                ->modalDescription('Isso vai importar todas as atividades (courseworks) do Google Classroom que ainda não foram importadas. Atividades sem tópico (turma) serão ignoradas.')
                ->modalSubmitActionLabel('Importar')
                ->action(function () {
                    try {
                        $syncService = new AtividadeSyncService(
                            app(GoogleMainService::class)
                        );

                        $stats = $syncService->syncAtividades();

                        Notification::make()
                            ->title('Importação concluída')
                            ->body("Criadas: {$stats['criadas']} | Ignoradas: {$stats['ignoradas']}")
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erro na importação')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\CreateAction::make()
                ->label('Nova Atividade')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalHeading('Criar Nova Atividade')
                ->modalDescription('Preencha os dados para criar uma nova atividade')
                ->modalSubmitAction(false)
                ->createAnother(false)
                ->successNotificationTitle('Atividade criada com sucesso!')
                ->closeModalByClickingAway(false)
                ->fillForm([
                    'descricao' =>
                    "Olá, professor(a)!\n" .
                        "A rotina semanal e os planos de ensino já estão disponíveis. Para acessá-los, siga as orientações a seguir.\n\n" .
                        "✅ Abra a ROTINA e complete com os objetos de conhecimento/habilidades e objetivos de aprendizagem.\n" .
                        "✅ Consulte os PLANOS DE ENSINO e prepare as aulas conforme as especificidades de cada turma, com as adequações necessárias aos estudantes.\n" .
                        "✅ Os documentos são salvos automaticamente.\n" .
                        "✅ Para imprimir, basta abrir o documento, acessar a aba \"Arquivo\" e clicar em \"Imprimir\", sem necessidade de salvar previamente.\n\n" .
                        "Bom trabalho! 🚀✨",
                ])
                ->after(function () {
                    Notification::make()
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

        $turmas = \App\Models\Turma::query()
            ->where('serie_id', $serieId)
            ->whereIn('escola_id', $escolas)
            ->with(['professores', 'escola'])
            ->get();

        $this->turmasDaSerie = $turmas;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Atividade $atividade */
        $atividade = $this->record;

        foreach ($this->turmasDaSerie as $turma) {
            $professores = $turma->professores
                ->where('escola_id', $turma->escola_id);

            foreach ($professores as $professor) {
                $atividade->turmas()->attach($turma->id, [
                    'professor_id' => $professor->id,
                ]);
            }
        }
    }
}
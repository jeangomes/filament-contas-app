<?php

namespace App\Filament\Resources\CreditCardBillResource\RelationManagers;

//use App\ManualVersionStatus;
//use App\ManualVersionTypes;
//use App\Models\ManualVersion;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\Storage;
use Filament\Support\Colors\Color;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $modelLabel = 'transação';
    protected static ?string $title = 'Transações';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Fieldset::make('Dados da versão')
                    ->schema([
                        Forms\Components\TextInput::make('version_number')
                            ->label('Versão')
                            ->required()
                            ->maxLength(255),
                        /*Forms\Components\Select::make('type_content')
                            ->label('Tipo de conteúdo')
                            ->options(ManualVersionTypes::class)
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('status')
                            ->options(ManualVersionStatus::class)
                            ->required(),*/
                    ])
                    ->columns(3),
                Forms\Components\FileUpload::make('stored_file')
                    ->columnSpanFull()
                    ->label('Arquivo')
                    ->acceptedFileTypes(['application/pdf'])
                    ->storeFileNamesIn('uploaded_file')
                    ->required(fn (Get $get): bool => $get('type_content') === 'UPLOAD')
                    ->visible(fn (Get $get): bool => $get('type_content') === 'UPLOAD'),
/*                Forms\Components\Repeater::make('topics')
                    ->label('Tópicos')
                    ->columnSpanFull()
                    ->relationship('topics')
                    ->schema([
                        Forms\Components\TextInput::make('topic_number')->label('Nº.')->required(),
                        Forms\Components\TextInput::make('topic_title')->label('Título')->required(),
                        Forms\Components\RichEditor::make('topic_text')->label('Texto')
                            ->columnSpanFull()
                            ->required(),
                    ])
                    ->orderColumn('topic_number')
                    ->required(fn (Get $get): bool => $get('type_content') === 'HTML')
                    ->visible(fn (Get $get): bool => $get('type_content') === 'HTML')
                    ->grid(1)
                    ->columns(2)*/
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordUrl(null)->recordAction(null)
            //->emptyStateHeading('No posts yet')
            ->emptyStateDescription('Depois que você salvar a primeira versão, ela aparecerá aqui.')
           // ->recordTitle(fn (ManualVersion $record): string => " - Manual: {$record->manual->title} | Versão: {$record->version_number}")
            ->recordTitleAttribute('version_number')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')->label('Data'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->summarize(Count::make()),
                Tables\Columns\TextColumn::make('parcelas'),
                Tables\Columns\TextColumn::make('amount')->money('BRL')->label('Valor')
                    ->summarize(Sum::make()),
                Tables\Columns\ToggleColumn::make('common_expense')->label('Gasto em comum')
                    ->summarize(
                        Count::make()->query(fn (QueryBuilder $query) => $query->where('common_expense', true)),
                    ),
                Tables\Columns\ToggleColumn::make('individual_expense')->label('Gasto Individual')
                    ->summarize(
                        Count::make()->query(fn (QueryBuilder $query) => $query->where('individual_expense', true)),
                    ),
                /*Tables\Columns\TextColumn::make('stored_file')->label('Visualizar')
                    ->url(fn (ManualVersion $record): string => $record->stored_file ? Storage::url($record->stored_file) : '')
                    ->state(function (ManualVersion $record) {
                        return $record->stored_file ? 'Link' : '';
                    })
                    ->color(Color::Blue)
                    //->extraAttributes(['class' => 'text-primary-600'])
                    ->placeholder('-')
                    //->description(fn (Manual $record): string => $record->name.'cucu'),
                    ->openUrlInNewTab(),*/
                //Tables\Columns\TextColumn::make('topics_count')->counts('topics')->label('Tópicos'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        //dd($data);
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->hiddenLabel(),
                Tables\Actions\EditAction::make()->hiddenLabel(),
                Tables\Actions\DeleteAction::make()->hiddenLabel(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

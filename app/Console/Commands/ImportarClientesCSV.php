<?php

namespace App\Console\Commands;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Console\Command;
use League\Csv\CharsetConverter;
use League\Csv\Reader;

class ImportarClientesCSV extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importar:clientes {arquivo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa clientes a partir de um arquivo CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $arquivo = $this->argument('arquivo');

        if (!file_exists($arquivo)) {
            $this->error("Arquivo não encontrado: $arquivo");
            return 1;
        }

        try {
            $csv = Reader::createFromPath($arquivo, 'r');
            $csv->setDelimiter(','); // Alterado para vírgula
            $csv->setHeaderOffset(0); // Primeira linha como cabeçalho
            CharsetConverter::addTo($csv, 'ISO-8859-1', 'UTF-8');

            $contador = 0;

            foreach ($csv as $linha) {
                // Verifica se o status é válido
                if ((int)$linha["usu_id"] !== 33 || (int)$linha["desativado"] !== 0) {
                    continue; // Pula se não atender os critérios
                }

                $mensagem = sprintf(
                    'Bom dia, *%s* seu plano expira hoje. Queria saber se tem interesse em renovar?',
                    $linha['nome']
                );

                $cliente = Client::firstOrCreate(
                    [
                        'name' => $linha['nome'],
                        'vencimento' => $linha['dataVencimento'],
                        'value_mensalidade' => $linha['valorCobrado'],
                        'status' => 'Ativo',
                        'user_id' => 2,
                        'phone' => preg_replace('/[^\d]/', '', $linha['celular']),
                        'cobrar' => (int)$linha['loginDuplo'],
                        'msg_enviar' => $mensagem,
                        'referencia' => $linha['usuario'],
                        'observation' => $linha['obs'],
                    ]
                );

                if ($cliente->wasRecentlyCreated) {
                    $contador++;
                    $this->info("Cliente '{$linha['nome']}' importado com sucesso!");
                } else {
                    $this->info("Cliente '{$linha['nome']}' já existe, pulando...");
                }
            }

            $this->info("Importação concluída com sucesso! Total de clientes importados: $contador");
            return 0;
        } catch (\Exception $e) {
            $this->error("Erro ao processar o arquivo: " . $e->getMessage());
            return 1;
        }
    }
}

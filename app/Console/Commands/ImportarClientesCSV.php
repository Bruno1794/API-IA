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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $arquivo = $this->argument('arquivo');

        if (!file_exists($arquivo)) {
            $this->error("Arquivo não encontrado: $arquivo");
            return 1;
        }

        $csv = Reader::createFromPath($arquivo, 'r');
        $csv->setDelimiter(';'); // <- AQUI
        $csv->setHeaderOffset(0); // primeira linha como cabeçalho
        CharsetConverter::addTo($csv, 'ISO-8859-1', 'UTF-8');

        foreach ($csv as $linha) {
            // Verifica se o status é 1
            if ((int)$linha["usu_id"] !== 33 || (int)$linha["desativado"] !== 0) {
                continue; // Pula se não for 1
            }

            $mensagem = sprintf(
                'Bom dia, *%s* seu plano  expira hoje. Queria saber se tem interesse em renovar?',
                $linha['nome'],

            );
            //'date_desativado' => $linha['data_desativado'] ?? null,

            Client::firstOrCreate(

                [
                    'name' => $linha['nome'],
                    'vencimento' => $linha['dataVencimento'],
                    'value_mensalidade' => $linha['valorCobrado'],
                    'status' => "Inativo",
                    'user_id' => '2',
                    'phone' => preg_replace('/[^\d]/', '', $linha['celular']),
                    'cobrar' => $linha['status_cobranca']? 0 : 1,
                    'msg_enviar' => $mensagem,
                    'referencia' => $linha['usuario'],
                    'observation' => $linha['obs'],

                ]
            );

            $this->info('Importação concluída com sucesso!');
        }
        return 0;

    }

}
//php artisan importar:clientes storage/app/cliente.csv

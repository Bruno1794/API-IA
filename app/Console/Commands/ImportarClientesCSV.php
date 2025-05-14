<?php

namespace App\Console\Commands;

use App\Models\Client;
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

            Client::firstOrCreate(

                [
                    'name' => $linha['nome'],
                    'vencimento' => $linha['dataVencimento'],
                    'value_mensalidade' => $linha['valorCobrado'],
                    'type_client' => 'Cliente',
                    'user_id' => '1',
                    'phone' => $linha['celular'],
                    'cobranca' => $linha['status_cobranca']
                ]
            );

            $this->info('Importação concluída com sucesso!');
        }
        return 0;

    }

}
//php artisan importar:clientes storage/app/cliente.csv

<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        Agent::create([
            'pergunta' => "Como funciona?",
            'resposta' => "Criamos automação para empresas, software que ajuda no seu dia dia. Chatboot, agendamentos
            preenchimentos de documentos automatizado e muito mais",
        ]);

        Agent::create([
            'pergunta' => "Qual valor?",
            'resposta' => "Valor da automatização varia de acordo com seu projeto.",
        ]);

        Agent::create([
            'pergunta' => "Preciso de hospedagem ou vps?",
            'resposta' => "Sim e necessario um VPS de 4Gb ou mais dependendo do proejeto",
        ]);

        Agent::create([
            'pergunta' => "Quanto tempo ?",
            'resposta' => "tempo varia de acordo com seu projeto.",
        ]);

        Agent::create([
        'pergunta' => "Formas de Pagamento",
        'resposta' => "Pix ou Cartao. Para o inicio do projeto será necessario o pagamento de 50% do valor.",
          ]);


     /*   Agent::create([
            'pergunta' => "Como funciona?",
            'resposta' => "O sistema funciona atraves da internet, vc precia ter uma TV Smart, ou aparelhos android tipo
            firestick tv da amazon ou tv box android. TVs Smart antigas nao funciona.",
        ]);

        Agent::create([
            'pergunta' => "Quais o valores?",
            'resposta' => "Mensal: R$30,00 Trimestral: R$85,00 Semestral: R$150,00 Anual: R$280,00",
        ]);

        Agent::create([
            'pergunta' => "Posso por em ate quantos aparelhos ?",
            'resposta' => "O sistema funciona em apenas uma parelho. Caso queira por o mesmo ponto e mais aparelho
            nao pode assistir simulteniamente e o aplicatico do dispositivo secundario e cobrado a parte.",
        ]);

        Agent::create([
            'pergunta' => "Quanto tempo de teste ?",
            'resposta' => "Não oferecemos tempo de teste. Mais te damos uma garantia de até 5hrs apos a instação do sistema
            em sua tv. Se nesse tempo se nao gostar é só solicitar o reembolso. clique nesse link e gere seu acesso
            agora mesmo: https://codeacode.com.br/",
        ]);

        Agent::create([
            'pergunta' => "Quais as formas de pagamento",
            'resposta' => "Cartao de credito e PIX",
        ]);


        Agent::create([
            'pergunta' => "Quero contratar ou Assinar",
            'resposta' => "vou te direcionar para pagina de pagamento, apos concluir imediatamente seu acesso será criado.
            clique nesse link: https://codeacode.com.br/",
        ]);

        Agent::create([
            'pergunta' => "Televiões Samsung e LG",
            'resposta' => "Smatv samsung e LG é necessario baixar o aplicativo IBOPLAY, ao baixar o aplicativo informar o
            codigo MAC e KEY, que fica no rodape do aplicativo, caso nao aparece clica na opçao alterar servidor",
        ]);

        Agent::create([
            'pergunta' => "Celular Android ou Smartv Android",
            'resposta' => "Baixar o aplicativo CAP PLAYER e infomar o MAC e KEY para criar o acesso",
        ]);

        Agent::create([
            'pergunta' => "Para iphone Ios",
            'resposta' => "Baixar o aplicativo VU PLAYER PRO, ao baixar informar o codigo MAC e KEY dele",
        ]);

        Agent::create([
            'pergunta' => "Oferecemos os melhores aplicativo da atualidade",
            'resposta' => "Oferecemos aplicativos premium sem custo adicional para o melhor funcionamento do sistema
            em sua casa. Esse aplicativos na maioria dos dispositvos sao pagos porem nao repassamos o valor para o
            usuario. Apos se tornar um assinante tera contato com o suporte que atende todos os dias, grupo de clientes
            para informações, programação e promoções. Indicando o nosso serviço voce e bonificado com um mes de brinde
            em seu acesso."
        ]);*/
    }
}

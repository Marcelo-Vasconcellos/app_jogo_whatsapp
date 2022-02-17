<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Positus\Laravel\Client;
use App\Models\Jogador;

class ResolveWhatsappRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
/*
        {
            "contacts": [{
              "profile": {
                "name": "Kerry Fisher"
                },
              "wa_id": "16315551234"
            }],
            "messages":[{
              "from": "16315551234",
              "id": "ABGGFlA5FpafAgo6tHcNmNjXmuSf",
              "timestamp": "1518694235",
              "text": {
                "body": "Hello this is an answer"
              },
              "type": "text"
            }]
        }         
*/
        if (array_key_exists('messages', $this->data)) {

            $type = $this->data['messages'][0]['type'];

            if ($type == 'text') {

                //Bloco de dados tipo TEXT da API Bussiness
                $name = $this->data['contacts'][0]['profile']['name'];       
                $wa_id = $this->data['contacts'][0]['wa_id'];
                $from = $this->data['messages'][0]['from'];
                $id = $this->data['messages'][0]['id'];
                $timestamp = $this->data['messages'][0]['timestamp'];
                $body = $this->data['messages'][0]['text']['body'];

                //conexão com a SAND-BOX Positus 
                $number = Client::number('6334ea09-d3fe-4689-8acb-684eb0d0ec78', true);

                $query = Jogador::where('wa_id','=', $wa_id)->where('finalizado','<>', 'sim')->get()->toArray();

                if (empty($query)) {
                    logger('entrou no CREATE '.$wa_id);
                    $message = $number->sendText('+'.$wa_id, $name.' Vamos jogar?');
                    $message = $number->sendText('+'.$wa_id, 'Objetivo: Adivinhe um número ALEATÓRIO SORTEADO pelo sistema, o valor sorteado estará entre 1 e 10, tente adivinhar o número na menor quantidade de tentativas e no menor tempo possível.');
                    $message = $number->sendText('+'.$wa_id, 'ENVIE um número entre 1 e 10');
                    Jogador::create([
                        'wa_id'=>$wa_id,
                        'name'=>$name,
                        'tentativas'=>0,
                        'duracao'=>0,
                        'microtime'=>microtime(true),
                        'num_sorteado'=>random_int(1,10),
                        'finalizado'=>'nao'
                    ]);
                }else{
                    logger('entrou no UPDATE '.$query[0]['wa_id']);
                    if ($body<1 || $body>10) {
                        $message = $number->sendText('+'.$wa_id, ' Ops, SOMENTE números entre 1 e 10, :)');
                    }else{

                        if ($body == $query[0]['num_sorteado']) {
                            $gravar=Jogador::find($query[0]['id']);
                            $tentativas = $query[0]['tentativas'] + 1;
                            $microtimeNew = microtime(true);
                            $microtimeOld = $query[0]['microtime'];
                            $duracao = round($microtimeNew - $microtimeOld,2);

                            $gravar->FILL([
                                'wa_id'=>$wa_id,
                                'name'=>$name,
                                'tentativas'=>$tentativas,
                                'microtime'=>$microtimeNew,
                                'duracao'=>$duracao,
                                'finalizado'=>'sim'
                            ]);
                            $gravar->save();
                            $message = $number->sendText('+'.$wa_id, ' PARABÉNS O NÚMERO SORTEADO FOI '.$query[0]['num_sorteado'].' e você utilizou '.$tentativas.' tentativas em '.$duracao.' segundos.');
                            $message = $number->sendText('+'.$wa_id, ' para jogar de novo, envie SIM, ou na verdade qualquer mensagem reiniciará o jogo kkk ');
                            $message = $number->sendText('+'.$wa_id, ' ---- VEJA O RANKING DOS JOGADORES ---');
                            $message = $number->sendText('+'.$wa_id, ' -- SORTUDOS <que acertaram de primeira> --');

                            $i = 0;
                            $rank = Jogador::orderBy('duracao','asc')->where('tentativas','=',1)->where('finalizado','=','sim')->get()->toArray();
                            if (empty($rank)) {
                                $message = $number->sendText('+'.$wa_id, ' -- <sem sorudos no momento> --');
                            }else{
                                foreach($rank as $r){
                                    $message = $number->sendText('+'.$wa_id, '('.$rank[$i]['name'].') em ('.$rank[$i]['duracao'].') segundos');
                                    $i++;
                                }
                            }

                            $message = $number->sendText('+'.$wa_id, ' -- Ranking Geral das 5 melhores jogadas --');
                            $i = 0;
                            $rank = Jogador::orderBy('tentativas','asc')->orderBy('duracao','asc')->where('tentativas','>',1)->where('finalizado','=','sim')->get()->toArray();
                            foreach($rank as $r){
                                $message = $number->sendText('+'.$wa_id, $rank[$i]['name'].' Tentativas ('.$rank[$i]['tentativas'].') em ('.$rank[$i]['duracao'].') segundos');
                                $i++;
                                if($i>4) {break;}
                            }

                            $message = $number->sendText('+'.$wa_id, ' -As 5 melhores jogadas de '.$name.'-');
                            $i = 0;
                            $rank = Jogador::orderBy('tentativas','asc')->orderBy('duracao','asc')->where('wa_id','=',$wa_id)->where('tentativas','>',1)->where('finalizado','=','sim')->get()->toArray();
                            foreach($rank as $r){
                                $message = $number->sendText('+'.$wa_id, 'Qtd.Tentativas ('.$rank[$i]['tentativas'].') em ('.$rank[$i]['duracao'].') segundos');
                                $i++;
                                if($i>4) {break;}
                            }



                        }else{
                        
                            if ($body < $query[0]['num_sorteado']) {
                                $message = $number->sendText('+'.$wa_id, ' Quase, tente um número MAIOR.');
                            }
                            
                            if ($body > $query[0]['num_sorteado']) {
                                $message = $number->sendText('+'.$wa_id, ' Quase, tente um número MENOR.');
                            }

                            $gravar=Jogador::find($query[0]['id']);
                            $tentativas = $query[0]['tentativas'] + 1;
                            $microtimeNew = microtime(true);
                            $microtimeOld = $query[0]['microtime'];
                            $duracao = round($microtimeNew - $microtimeOld,2);

                            $gravar->FILL([
                                'wa_id'=>$wa_id,
                                'name'=>$name,
                                'tentativas'=>$tentativas,
                                'microtime'=>$microtimeNew,
                                'duracao'=>$duracao
                            ]);
                            $gravar->save();
                            $message = $number->sendText('+'.$wa_id, 'ENVIE +1 número entre 1 e 10');
                        }
                    }
                }

/*                
                $number = Client::number('6334ea09-d3fe-4689-8acb-684eb0d0ec78', true);
                $message = $number->sendText('+'.$wa_id,'Nome => '.$name);
                $message = $number->sendText('+'.$wa_id,'Wa_id => '.$wa_id);
                $message = $number->sendText('+'.$wa_id,'From => '.$from);
                $message = $number->sendText('+'.$wa_id,'Id => '.$id);
                $message = $number->sendText('+'.$wa_id,'Timestamp => '.$timestamp);
                $message = $number->sendText('+'.$wa_id,'Body => '.$body);
                $message = $number->sendText('+'.$wa_id,'Type => '.$type);
*/                
            }    
            
        } else {
            // processar status da mensagem
        }
    }
}

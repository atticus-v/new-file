
<?php

namespace App\Jobs;

use App\Helpers\StockHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Predis\Client;

class Convertible implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    public $info;
    public $helper;
    public $client;



    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($info)
    {
        //
        $this->info = json_decode($info,1);
        $this->helper = new StockHelper();
        $this->client = new Client();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cid = $this->info['cid'];
        $type = 'day';
        $serverBaseUrl = 'http://web.ifzq.gtimg.cn/appstock/app/fqkline/get?_var=';
        $codeStr = $this->helper->stockType($cid).$cid;

        $serverUrl = $serverBaseUrl . '&param='.$codeStr.','.$type.',,,60,qfq&r='.rand(1,10000000);
        $fileData = file_get_contents($serverUrl);
        $fileArray = json_decode($fileData, 1);
        $convertibleLine = [];
        if(isset($fileArray['data'][$codeStr][$type])){
            $source = $fileArray['data'][$codeStr][$type];
        }else{
            $source = $fileArray['data'][$codeStr]['qfq'.$type];
        }
        foreach($source as $record){
            //if(strtotime($record[0])>strtotime('-1 year')){
            $convertibleLine[] = [
                date('Ymd',strtotime($record[0])),
                $record[1],  //open
                $record[3],  //high
                $record[4],  //low
                $record[2],  //close
                $record[5]   //vol
            ];
            //}
        }


        /*$log = [
            $this->isNew($convertibleLine),  //是否新债
            $this->haveZtTwoWeek($convertibleLine),  //2周内正股有涨停（取14个交易日）
            0,  //最近是否有异动(4%以上波动幅度)
            0
        ];*/


        $code = $this->info['code'];
        $codeStr = $this->helper->stockType($code).$code;
        $serverUrl = $serverBaseUrl . '&param='.$codeStr.','.$type.',,,60,qfq&r='.rand(1,10000000);
        $fileData = file_get_contents($serverUrl);
        $fileArray = json_decode($fileData, 1);
        $stockLine = [];
        if(isset($fileArray['data'][$codeStr][$type])){
            $source = $fileArray['data'][$codeStr][$type];
        }else{
            $source = $fileArray['data'][$codeStr]['qfq'.$type];
        }
        foreach($source as $record){
            //if(strtotime($record[0])>strtotime('-1 year')){
            $stockLine[] = [
                date('Ymd',strtotime($record[0])),
                $record[1],  //open
                $record[3],  //high
                $record[4],  //low
                $record[2],  //close
                $record[5]   //vol
            ];
            //}
        }


        foreach($convertibleLine as $key=>$record){
            if($key>0){
                $yClose = $convertibleLine[$key-1][4];
                $zf = ($record[4]-$yClose)/$yClose;
                $maxZf = ($record[2]-$yClose)/$yClose;
                $minZf = ($record[3]-$yClose)/$yClose;
                $openZf = ($record[1]-$yClose)/$yClose;
                $redisKey = 'info_'.$record[0];


                $info = [
                    'name'=>$this->info['cname'],
                    'code'=>$this->info['cid'],
                    'sid'=>$this->info['code'],
                    'sname'=>$this->info['name'],
                    'openZf'=>round($openZf*100,2),
                    'zf'=>round($zf*100,2),
                    'maxZf'=>round($maxZf*100,2),
                    'minZf'=>round($minZf*100,2),
                    'isZt'=>$this->isZt($this->info['code'],$record[0],$stockLine)
                ];
                //print_r($info);
                $this->client->hmset($redisKey,[$this->info['cid']=>json_encode($info)]);
            }
        }
        return true;


    }


    public function isNew($data){
        return count($data)<4?1:0;
    }

    //2周内正股有涨停
    public function haveZtTwoWeek(){
        return 1;
    }

    public function hotRecently($data){

    }


    //判断正股当天是否涨停
    public function isZt($code,$day,$source){
        $index = -1;
        $result = false;
        foreach($source as $key=>$record){
            if($record[0] === $day && $key>0){
                if((new StockHelper())->stockZtPrice($code,$source[$key-1][4]) == $record[4] || (($source[$key][4]-$source[$key-1][4])/$source[$key-1][4])>0.11){
                    $result = 1;
                }
            }
        }
        return $result;
    }


}

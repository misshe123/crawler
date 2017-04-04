<?php

/**
 * Created by PhpStorm.
 * User: Smile
 * Date: 2017/3/30
 * Time: 11:45
 */

namespace Crawler\Controller;


class CrawlerController
{

    public function index()
    {
        $values = [];
        $detailUrl = M('task');
        $r = iconv('gbk', 'utf-8', file_get_contents('http://data.eastmoney.com/DataCenter_V3/gdhs/GetList.ashx?reportdate=&market=&changerate==&range==&pagesize=1000&page=1&sortRule=-1&sortType=NoticeDate&js=var%20YoNVdQFV&param=&rt=49675151'));
        $data = json_decode(strstr($r, "{"), true);
        for ($i = 0; $i < count($data['data']); $i++) {
            foreach ($data['data'][$i] as $key => $value) {
                $values[0][$key][$i] = $value;
            }
        }
        if ($values) {
            $i = 0;
            foreach ($values[0]['SecurityCode'] as $value) {
                $codeDetail[$i] = $value;
                $u[$i] = "http://data.eastmoney.com/DataCenter_V3/gdhs/GetDetial.ashx?code=$codeDetail[$i]&js=var%20SSWXVzBq&pagesize=50&page=1&sortRule=-1&sortType=EndDate&param=&rt=49675378";

                //获取到的url对应存入数据库
                $url['urlId'] = $codeDetail[$i];
                $url['detailUrl'] = $u[$i];
                if ($detailUrl->where(['urlId' => $codeDetail[$i]])->find()) {
                    continue;
                } else {
                    $add = $detailUrl->add($url);
                }

                if (empty($u)) {
                    $url['statues'] = 0;//未获取到地址，进程为开始
                } elseif ($add) {
                    $url['statues'] = 1;//存数据库成功
                } else {
                    $url['statues'] = 2;//存数据库失败
                }
                $detailUrl->add($url['statues']);
                $i++;
            }
        }

        $db = $this->crawlerDb($values);
        if ($db) {
            echo 'success';
        } else {
            echo 'fail';
        }
    }

    public function multiCurl()
    {
        $detailUrl = M('task');
        $values = [];
        //读数据库url
        $ReadUrlDetail = [];
        $codeDetail = [];//对应代码code
        $r = iconv('gbk', 'utf-8', file_get_contents('http://data.eastmoney.com/DataCenter_V3/gdhs/GetList.ashx?reportdate=&market=&changerate==&range==&pagesize=10000&page=1&sortRule=-1&sortType=NoticeDate&js=var%20YoNVdQFV&param=&rt=49675151'));
        $data = json_decode(strstr($r, "{"), true);
        for ($i = 0; $i < count($data['data']); $i++) {
            foreach ($data['data'][$i] as $key => $value) {
                $values[0][$key][$i] = $value;
            }
        }
        for ($i = 0; $i < count($values[0]['SecurityCode']); $i++) {
            $codeDetail[$i] = $values[0]['SecurityCode'][$i];
            $ReadUrl[$i] = $detailUrl->where(['urlId' => $codeDetail[$i]])->find();//读取从数据库存的url
            $ReadUrlDetail[$i] = $ReadUrl[$i]['detailUrl'];
        }

        //curl 获取URL对应的数据存文件里面
        $conn = [];
        $res = [];
        $string = '';
        $mh = curl_multi_init();

        foreach ($ReadUrlDetail as $i => $url) {
            $conn[$i] = curl_init($url);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_multi_add_handle($mh, $conn[$i]);//向curl批处理会话中添加单独的curl句柄
        }
        do {
            $status = curl_multi_exec($mh, $active);
            $info = curl_multi_info_read($mh);
            /*  if (false !== $info) {
                  var_dump($info);
              }*/
        } while ($status === CURLM_CALL_MULTI_PERFORM || $active);

        foreach ($ReadUrlDetail as $i => $url) {
            $res[$i] = curl_multi_getcontent($conn[$i]);
            $str = strstr($res[$i], "{");
            $string .= "code=" . $codeDetail[$i] . ' ' . $str . "\r\n";
            curl_close($res[$i]);
        }
        curl_multi_remove_handle($mh, $info['handle']);
       $file= file_put_contents('detailCurl.txt', iconv('GBK', 'utf-8', $string));
        if ($file){
            file_put_contents('multiCurlLog.txt',date('Y-m-d H-i-s').'任务执行成功');
        }else{
            file_put_contents('multiCurlLog.txt',date('Y-m-d H-i-s').'任务执行和失败');

        }
        curl_close($mh['handle']);

    }

    public function readText()
    {
        $data = fopen("detailCurl.txt", 'r');
        if ($data) {
            $i = 0;
            while (!feof($data)) {  //一个json对应一个详情列表，每次读取一个json
                $read = fgets($data);
                $code[$i] = strstr($read, "{", true);
                $readText = json_decode(strstr($read, "{"), true);
                $this->detailDB($readText['data'], $code[$i]);//将数据插入数据库
                $i++;
            }
        }
        fclose($data);
    }

    public function detailDB($values, $code)
    {
        $detail = M('detail');
        $detailDb['DetailGroupUID'] = $code;//DetailGroup
        for ($i = 0; $i < count($values); $i++) {
            $detailDb['HolderNum'] = $values[$i]['HolderNum'];
            $detailDb['PreviousHolderNum'] = $values[$i]['PreviousHolderNum'];
            $detailDb['HolderNumChange'] = $values[$i]['HolderNumChange'];
            $detailDb['HolderNumChangeRate'] = $values[$i]['HolderNumChangeRate'];
            $detailDb['RangeChangeRate'] = $values[$i]['RangeChangeRate'];
            $detailDb['EndDate'] = $values[$i]['EndDate'];
            $detailDb['HolderAvgCapitalisation'] = $values[$i]['HolderAvgCapitalisation'];
            $detailDb['HolderAvgStockQuantity'] = $values[$i]['HolderAvgStockQuantity'];
            $detailDb['TotalCapitalisation'] = $values[$i]['TotalCapitalisation'];
            $detailDb['CapitalStock'] = $values[$i]['CapitalStock'];
            $detailDb['NoticeDate'] = $values[$i]['NoticeDate'];
            $detailDb['CapitalStockChange'] = $values[$i]['CapitalStockChange'];
            $detailDb['CapitalStockChangeEvent'] = $values[$i]['CapitalStockChangeEvent'];
            $detailDb['ClosePrice'] = $values[$i]['ClosePrice'];

            if ($detail->where(['HolderNum' => $values[$i]['HolderNum'], ['PreviousHolderNum' => $values[$i]['PreviousHolderNum']]])->find()) {
                continue;
            } else {
                if (!$detail->add($detailDb)) {
                    file_put_contents('E:\projects\User\detailDb.txt', date('Y-m-d H:i:s') . ' ' . "Error: 数据插入失败" . "\r\n", FILE_APPEND);
                }
            }
        }
    }

    public function crawlerDb($values)
    {
        $db = M('crawler');
        for ($i = 0; $i < count($values[0]['SecurityCode']); $i++) {
            $Dbdate['SecurityCode'] = $values[0]['SecurityCode'][$i];
            $Dbdate['SecurityName'] = $values[0]['SecurityName'][$i];
            $Dbdate['LatestPrice'] = $values[0]['LatestPrice'][$i];
            $Dbdate['PriceChangeRate'] = $values[0]['PriceChangeRate'][$i];
            $Dbdate['HolderNum'] = $values[0]['HolderNum'][$i];
            $Dbdate['PreviousHolderNum'] = $values[0]['PreviousHolderNum'][$i];
            $Dbdate['HolderNumChange'] = $values[0]['HolderNumChange'][$i];
            $Dbdate['HolderNumChangeRate'] = $values[0]['HolderNumChangeRate'][$i];
            $Dbdate['RangeChangeRate'] = $values[0]['RangeChangeRate'][$i];
            $Dbdate['EndDate'] = $values[0]['EndDate'][$i];
            $Dbdate['PreviousEndDate'] = $values[0]['PreviousEndDate'][$i];
            $Dbdate['HolderAvgCapitalisation'] = $values[0]['HolderAvgCapitalisation'][$i];
            $Dbdate['HolderAvgStockQuantity'] = $values[0]['HolderAvgStockQuantity'][$i];
            $Dbdate['TotalCapitalisation'] = $values[0]['TotalCapitalisation'][$i];
            $Dbdate['CapitalStock'] = $values[0]['CapitalStock'][$i];
            $Dbdate['NoticeDate'] = $values[0]['NoticeDate'][$i];
            $t = $db->add($Dbdate);
            if (!$t) {
                die;
            }
        }
        return $this;
    }
}
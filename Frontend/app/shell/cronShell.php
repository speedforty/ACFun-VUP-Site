<?php

namespace app\shell;
use biny\lib\Shell;

class cronShell extends Shell
{

    //默认路由index
    public function action_up()
    {
        //在00：00后运行，时间戳会是0点的-1秒，也就是昨日的23：59：59的数据
        $todayTimestamp = strtotime(date('Y-m-d'));
        //$todayTimestamp = strtotime("2020-9-14");
        $ydayTimestamp = $todayTimestamp - 86400;
        $upListDataset = $this->upDetailDAO->filter([
            '<'=>array('add_date'=> $todayTimestamp)
        ])->query();

        $updateSet = [];
        foreach ($upListDataset as $k => $upData){
            $rawLatestData = $this->upRawDataDAO->filter([
                'uperid'=>$upData['uperid'],
                '<='=>array('up_date'=> $todayTimestamp)
            ])->order(array('up_date'=>'DESC'))->limit(1)->query();
            $rawOldestData = $this->upRawDataDAO->filter([
                'uperid'=>$upData['uperid'],
                '>='=>array('up_date'=> $ydayTimestamp)
            ])->order(array('up_date'=>'ASC'))->limit(1)->query();
            if($rawLatestData && $rawOldestData){
                $followersAdded = $rawLatestData[0]['followers'] - $rawOldestData[0]['followers'];
                $followingAdded = $rawLatestData[0]['following'] - $rawOldestData[0]['following'];
                $updateSet[] = array(
                    "uperid"    => $upData['uperid'],
                    "add_date"  => $todayTimestamp - 1,
                    "followers" => $rawLatestData[0]['followers'],
                    "following" => $rawLatestData[0]['following'],
                    "followers_change" => $followersAdded,
                    "following_change" => $followingAdded,
                );
            }
        }
        $result = $this->upCronDataDAO->addList($updateSet);
        return json_encode(array("result" => $result, "data" => $updateSet));
    }
    
    public function action_live()
    {
        //在00：00后运行，时间戳会是0点的-1秒，也就是昨日的23：59：59的数据
        $todayTimestamp = strtotime(date('Y-m-d'));
        //$todayTimestamp = strtotime("2020-11-12");
        $ydayTimestamp = $todayTimestamp - 86400;
        $upListDataset = $this->upDetailDAO->filter([
            '<'=>array('add_date'=> $todayTimestamp)
        ])->query();


        $upLiveListToday = $this->upRawLiveDataDAO->filter([
            'isLive'=>1,
            '>='=>array('up_date'=> $ydayTimestamp),
            '<'=>array('up_date'=> $todayTimestamp)
        ])->distinct('uperid');
        
        $newArray = array();
        foreach ($upLiveListToday as $k => $lived){
            $newArray[$lived["uperid"]] = $lived["uperid"];
        }
        
        $updateSet = [];
        foreach ($upListDataset as $k => $upData){
            if($newArray[$upData['uperid']] == $upData['uperid']){
                $rawLatestData = $this->upRawLiveDataDAO->filter([
                    'uperid'=> $upData['uperid'],
                    'isLive'=> 1,
                    '>='=>array('up_date'=> $ydayTimestamp),
                    '<'=>array('up_date'=> $todayTimestamp)
                ])->max('onlineCount');
                if($rawLatestData){
                    $updateSet[] = array(
                    "uperid"    => $upData['uperid'],
                    "add_date"  => $todayTimestamp - 1,
                    "onlineCount" => $rawLatestData,
                    "isLive" => 1,
                );
                }
            }else{
                $updateSet[] = array(
                    "uperid"    => $upData['uperid'],
                    "add_date"  => $todayTimestamp - 1,
                    "onlineCount" => 0,
                    "isLive" => 0,
                );
            }
        }
        $result = $this->upLiveDataCronDAO->addList($updateSet);
        return json_encode(array("result" => $result, "data" => $updateSet));
    }
}
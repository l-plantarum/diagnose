<?php


namespace Drupal\diagnose\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class ResultController extends ControllerBase {
    private $total, $message, $sd, $average, $deviation, $point, $classes;
	private $full, $stdev, $total_deviation, $fullscore, $total_average;


    protected function getModuleName() {
        return 'diagnose';
    }

    public function show_result() {
        $qp = \Drupal::request()->query->all();
        if (array_key_exists("query", $qp)) {
            $uuid = $qp["query"];
        }
        else {
            #TODO: 何も表示されていない
            $retval["#message"] = "診断プログラムから動かせ";
            return $retval;
        }
        // diagnoseテーブル
        $database = \Drupal::database();
        $query = $database->query("select file, did, message, average, stdev, total , deviation, full from {diagnose} where uuid=:uuid", [":uuid" => $uuid]);
        $pres = $query->fetchAssoc();
        $this->total = $pres["total"];
        $this->message = $pres["message"];
        $this->sd = $pres["stdev"];
		$this->total_average = $pres["average"];
        $this->total_deviation = $pres["deviation"];
	    $this->fullscore = $pres["full"];
		$file = $pres["file"];
		$did = $pres["did"];
        $query = $database->query("select item, point, average, full, stdev, deviation from {diagnose_item} where uuid=:uuid", [":uuid" => $uuid]);
        $this->point = array();
		$this->full = array();
		$this->deviation = array();
        while ($sres = $query->fetchAssoc()) {
            $this->point[$sres["item"]] = $sres["point"];
			$this->full[$sres["item"]] = $sres["full"];
			$this->stdev[$sres["item"]] = $sres["stdev"];
			$this->deviation[$sres["item"]] = $sres["deviation"];
			$this->average[$sres["item"]] = $sres["average"];
        }

        // 文字部分
        $this->classes = join(",", array_keys($this->point));
        $absolute_path = \Drupal::service('file_system')->realpath("public://" . $file);
        $xml = simplexml_load_file($absolute_path);
		// 高得点率項目と低得点率項目
		$highpoint = -10000;
		$lowpoint = 10000;
		$highitem = $lowitem = "";
		foreach (array_keys($this->deviation) as $c) {
			if ($this->deviation[$c] > $highpoint) {
				$highpoint = $this->deviation[$c];
				$highitem = $c;
			}
			if ($this->deviation[$c] < $lowpoint) {
				$lowpoint = $this->deviation[$c];
				$lowitem = $c;
			}
		}

        $retval = [];
        $retval["#theme"] = "diagnose_theme_hook";
        $retval["#message"] = $this->message;
        $retval["#total_average"] = $this->total_average;
		$retval["#average"] = $this->average;
        $retval["#deviation"] = $this->deviation;
        $retval["#sd"] = $this->sd;
        $retval["#total"] = $this->total;
        $retval["#classes"] = $this->classes;
        $retval["#point"] = $this->point;
		$retval["#full"] = $this->full;
		$retval["#uuid"] = $uuid;
		$retval["#total_deviation"] = $this->total_deviation;
		$retval["#common"] = $xml->diagnosis->common;
		$retval["#pros"] = $xml->diagnosis->category->$highitem->pros;
		$retval["#advice"] = $xml->diagnosis->category->$lowitem->cons->advice;
		$retval["#amazon"] = $xml->diagnosis->category->$lowitem->cons->recommends;
		$retval["#movie"] = $xml->diagnosis->category->$lowitem->cons->movie;
		$retval["#fullscore"] = $this->fullscore;



		// TODO: シェアボタンの内容
		$req = \Drupal::request();
		$resulturl = $req->getSchemeAndHttpHost() . $req->getBasePath() . "/diagnose-result?query=" . $uuid;
		$retval["#chartmessage"] = "$resulturl\n防災意識診断をやってみたら，防災偏差値 $this->total_deviation でした!l";
		$retval["#charturl"] = \Drupal::request()->server->get('HTTP_REFERER');

		$retval["#diagnosemessage"] = "防災意識診断をやってみました!";
		$retval["#diagnoseurl"] = $req->getBasePath() . "/diagnose/" . $did;
		$retval["#mainsite"] = "防災情報博士";

        return $retval;
    }
}


<?php

namespace Drupal\diagnose\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Ramsey\Uuid\Uuid;

class DiagnoseForm extends FormBase {

	// uniqueなIDを振る
	public function getFormId() {
		return 'diagnose_form';
	}
	private function getXmlInfo($basename) {
		$absolute_path = \Drupal::service('file_system')->realpath("public://" . $name);
        return simplexml_load_file($absolute_path);
	}

	// Formの中身を返す
	public function buildForm(array $form, FormStateInterface $form_state, $name = NULL) {
	    if ($name == "0") {
			$name = "diag0.xml";
		}
		else if ($name == "1") {
			$name = "diagnosis1.xml";
		}
		$absolute_path = \Drupal::service('file_system')->realpath("public://" . $name);
        $xml = simplexml_load_file($absolute_path);
		$xmloptions = explode(",", $xml->options);
		// 選択肢
		$options = [];
		for ($i = 0; $i < count($xmloptions); $i++) {
			$options[3-$i] = $xmloptions[$i];
		}
		$question = $xml->questions->question;

		$form['number_wrapper'] = [
		   '#type' => 'item',
		   '#title' => $xml->title,
		   '#collapsible' => FALSE,
		   '#collapsed' => FALSE,
		];
		for ($i = 1; $i <= count($question); $i++) {
			if ($question[$i-1]->binary == "True") {
				$opt = [count($options)-1=>"はい", 0=>"いいえ"];
			}
			else {
				$opt = $options;
			}
		   $form['q'.$i] = [
		      "#type" => "radios",
			  "#title" => $question[$i-1]->title,
		   	  "#options" => $opt,
			  "#required" => TRUE,
		   ];
		}
		$form["submit"] = [
		    "#type" => "submit",
			"#value" => "診断",
		];
		return $form;
	}

	// 内容のチェックは不要．必須項目のチェックはデフォルトでされる．
	public function validateForm(array &$form,
		   					     FormStateInterface $form_state) {
	}

	// ポチした時の処理
	public function submitForm(array &$form, FormStateInterface $form_state) {
		// 引数の取得
		$args = $form_state->getBuildInfo()["args"];
		if ($args[0] == "0") {
			$name = "diag0.xml";
		}
		else if ($args[0] == "1") {
			$name = "diagnosis1.xml";
		}
		else {
			$name = $args[0];
		}

		// XMLの取得
		$absolute_path = \Drupal::service('file_system')->realpath("public://" . $name);
        $xml = simplexml_load_file($absolute_path);

		// 評価軸の初期化
		$classes = explode(",", $xml->classes);
		$point = [];
		$full = [];
		foreach ($classes as $c) {
			$point[$c] = 0;
			$full[$c] = 0;
		}

		// 点数集計
		$question = $xml->questions->question;
		$total = 0;
		$fullscore = 0;
	    for ($i = 1; $i <= count($question); $i++) {
			$class = strval(strval($question[$i-1]->class));
			$point[$class] += $form_state->getValue("q".$i);
			$full[$class] += (count(explode(",", $xml->options)) - 1);
			$total += $form_state->getValue("q".$i);
			$fullscore += (count(explode(",", $xml->options)) - 1);
		}

		// 全体評価
		$ave = $xml->diagnosis->average;
	    $sd = $xml->diagnosis->sd;
		if ($total >= $ave->total + $sd->total) {
			$message = strval($xml->diagnosis->high);
		}
		else if ($total <= $ave->total - $ave->sd) {
			$message = strval($xml->diagnosis->low);
		}
		else {
			$message = strval($xml->diagnosis->middle);
		}

		// 統計データ
		$uuid = Uuid::uuid1();
		$data = [
			"uuid" => $uuid,
			"file" => $name,
			"message" => $message,
			"average" => (float)(strval($ave->total)),
			"stdev" => (float)(strval($sd->total)),
			"total" => (float)$total,
			"full" => (float)$fullscore,
			"deviation" => (float)($total - $ave->total)*10/($sd->total)+50,
		];
		$connection = \Drupal::database();
		$connection->insert('diagnose')->fields($data)->execute();
		foreach ($classes as $c) {
			$subdata = [
				"uuid" => $uuid,
				"item" => $c,
				"point" => $point[$c],
				"average" => $ave->$c,
				"full" => $full[$c],
				"stdev" => (float)$sd->$c,
				"deviation" => (float)($point[$c]-$ave->$c)*10/($sd->$c)+50,
			];
			$connection->insert('diagnose_item')->fields($subdata)->execute();
		}

		$form_state->setRedirectUrl(Url::fromRoute("diagnose-result", 
 												   ["query" => $uuid]));
								    
		return;
	}
}
	

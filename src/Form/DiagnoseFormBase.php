<?php

namespace Drupal\diagnose\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

class DiagnoseFormBase extends EntityForm {

	protected $entityStorage;

    public function __construct(EntityStorageInterface $entity_storage) {
	  $this->entityStorage = $entity_storage;
	}

    public static function create(ContainerInterface $container) {
	  $form = new static($container->get('entity_type.manager')->getStorage("diagnose"));
	  $form->setMessaenger($container->get('messenger'));
	}

	// Formの中身を返す
	public function buildForm(array $form, FormStateInterface $form_state) {
      $form["file"] = [
	    "#type" => "textfield",
		"#title" => "filename basename",
		"#maglength" => 32,
		"#required" => TRUE,
	  ];
      $options = [
        "diag" => "diag",
		"test" => "test"
	  ];
	  $form["type"] = [
	    "#type" => "radios",
		"#title" => "診断タイプ",
		"#options" => $options,
		"#required" => TRUE,
	  ];
       $form["submit"] = [
		    "#type" => "submit",
			"#value" => "診断",
	   ];
	   return $form;
	}

	public function exists($entity_id, array $element, FormStateInterface $form_state) {
		$query = $this->entityStorage->getQuery();
		$result = $query->condition("id", $element["#field_prefix"].$entity_id)->execute();
		return (book)$result;
	}

	protected function actions(array $form, FormStateInterface $form_state) {
	  $actions = parent::actions($form, $form_state);
	  $actions["submit"]["#value"] = "保存";
	  return $actions;
	}

	// 内容のチェックは不要．必須項目のチェックはデフォルトでされる．
	public function validateForm(array &$form,
		   					     FormStateInterface $form_state) {
	}

	public function save(array $form, FormStateInterface $form_state) {
	  $diagnose = $this->getEntity();
	  $status = $diagnose->save();
	  $url = $diagnose->toUrl();
	  $edit_link = Link::fromTextAndUrl("Edit", $url)->toString();
	  // TODO: 表示内容を修正
	  if ($status == SAVED_UPDATED) {
	        $this->messenger()->addMessage($this->t('Robot %label has been updated.', ['%label' => $robot->label()]));
	        $this->logger('contact')->notice('Robot %label has been updated.', ['%label' => $robot->label(), 'link' => $edit_link]);
	  }
	  else {
	        $this->messenger()->addMessage($this->t('Robot %label has been added.', ['%label' => $robot->label()]));
	        $this->logger('contact')->notice('Robot %label has been added.', ['%label' => $robot->label(), 'link' => $edit_link]);
	  }
	  $form_state->setRedirect("entity.dignose.list");
	}

	// ポチした時の処理
	public function submitForm(array &$form, FormStateInterface $form_state) {
		// 引数の取得
		$args = $form_state->getBuildInfo()["args"];
		$type = $args[0];

		// XMLの取得
		$name = $args[1];
		$absolute_path = \Drupal::service('file_system')->realpath("public://" . $name);
        $xml = simplexml_load_file($absolute_path);

		// 評価軸の初期化
		$classes = explode(",", $xml->classes);
		$point = [];
		foreach ($classes as $c) {
			$point[$c] = 0;
		}

		// セッション変数の取得
		$store = \Drupal::service('tempstore.private')->get('diagnosis');

		// 点数集計
		$question = $xml->questions->question;
		$total = 0;
	    for ($i = 1; $i <= count($question); $i++) {
			$class = strval(strval($question[$i-1]->class));
			$point[$class] += $form_state->getValue("q".$i);
			$total += $point[$class];
		}
		$store->set('total', $total);
		$store->set('point', $point);
		$store->set('classes', $classes);

		// 全体評価
		$ave = $xml->diagnosis->average;
		if ($total >= $ave->total + $ave->sd) {
			$store->set("message", strval($xml->diagnosis->high));
		}
		else if ($total <= $ave->total - $ave->sd) {
			$store->set("message", strval($xml->diagnosis->low));
		}
		else {
			$store->set("message", strval($xml->diagnosis->middle));
		}

		// 統計データ
		$store->set("average", strval($ave->total));
		$store->set("sd", strval($ave->sd));
		$form_state->setRedirectUrl(Url::fromRoute("diagnose-result"));
		return;
	}
}
	

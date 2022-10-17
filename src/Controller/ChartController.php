<?php


namespace Drupal\diagnose\Controller;

use Drupal\Core\Controller\ControllerBase;

class ChartController extends ControllerBase {
    private $total, $message, $sd, $average, $deviation, $point, $classes;
	private $gd, $fontsp;
    private $p1, $p2, $p3, $c;
	private $full, $itemave;

    protected function getModuleName() {
        return 'diagnose';
    }

	# メモリの線と診断の線を書く
	function draw_triangle($s, $color) {
	    $fontsp = $this->fontsp;
    	imageline($this->gd, ($this->p1[0]-$this->c[0]-$fontsp) * $s[0] + $this->c[0] + $fontsp ,
                   ($this->p1[1]-$this->c[1]-$fontsp) * $s[0] + $this->c[1] + $fontsp ,
                   ($this->p2[0]-$this->c[0]-$fontsp) * $s[1] + $this->c[0] + $fontsp ,
                   ($this->p2[1]-$this->c[1]-$fontsp) * $s[1] + $this->c[1] + $fontsp ,
                   $color);
    	imageline($this->gd, ($this->p2[0]-$this->c[0]-$fontsp) * $s[1] + $this->c[0] + $fontsp ,
                   ($this->p2[1]-$this->c[1]-$fontsp) * $s[1] + $this->c[1] + $fontsp ,
                   ($this->p3[0]-$this->c[0]-$fontsp) * $s[2] + $this->c[0] + $fontsp ,
                   ($this->p3[1]-$this->c[1]-$fontsp) * $s[2] + $this->c[1] + $fontsp ,
                   $color);
    	imageline($this->gd, ($this->p1[0]-$this->c[0]-$fontsp) * $s[0] + $this->c[0] + $fontsp ,
                   ($this->p1[1]-$this->c[1]-$fontsp) * $s[0] + $this->c[1] + $fontsp ,
                   ($this->p3[0]-$this->c[0]-$fontsp) * $s[2] + $this->c[0] + $fontsp ,
                   ($this->p3[1]-$this->c[1]-$fontsp) * $s[2] + $this->c[1] + $fontsp ,
                   $color);
	}

	// グラフのタイトルを書く
	function draw_title($title, $pos, $color) {
	    imagettftext($this->gd, 14, 0, (int)$pos[0]-$this->fontsp,
					 (int)$pos[1], $color,
				     "/usr/share/fonts/ipa-gothic/ipag.ttf",
				     $title);
	}

	private function create_image() {
		$fontsp = $this->fontsp = 20;
		$this->c = [200, 200];

		$this->gd = imagecreate($this->c[0]*2+$fontsp*2+200, $this->c[1]*2+$fontsp*2);
		$bgcolor = imagecolorallocate($this->gd, 245, 222, 179);
		$black = imagecolorallocate($this->gd, 0, 0, 0);
		$ave = imagecolorallocate($this->gd, 77, 197, 58);
		$you = imagecolorallocate($this->gd, 215, 40, 71);
		$c_axis = $black;
		# 補助目盛用の灰色
		$c_step = imagecolorallocate($this->gd, 180, 160, 190);

		$this->p1 = [$this->c[0]+$fontsp, 0+$fontsp];
		$this->p2 = [$this->c[0]-$this->c[1]*sqrt(3)/2+$fontsp, $this->c[1]+$this->c[0]/2+$fontsp];
		$this->p3 = [$this->c[0]+$this->c[1]*sqrt(3)/2+$fontsp, $this->c[1]+$this->c[0]/2+$fontsp];

		// 3軸描画
		$res = imageline($this->gd, $this->c[0]+$fontsp, $this->c[1]+$fontsp, $this->p1[0], $this->p1[1], $c_axis);
		$res = imageline($this->gd, $this->c[0]+$fontsp, $this->c[1]+$fontsp, $this->p2[0], $this->p2[1], $c_axis);
		$res = imageline($this->gd, $this->c[0]+$fontsp, $this->c[1]+$fontsp, $this->p3[0], $this->p3[1], $c_axis);
		# 補助線を書く
		for ($i = 1; $i <= 3; $i++) {
			$s = [$i/3.0, $i/3.0, $i/3.0];
			$this->draw_triangle($s, $c_step);
		}
		# 各項目の平均値
		$subtitles = explode(",", $this->classes);
		$s = [$this->itemave[$subtitles[0]] / $this->full[$subtitles[0]],
              $this->itemave[$subtitles[1]] / $this->full[$subtitles[1]],
			  $this->itemave[$subtitles[2]] / $this->full[$subtitles[2]]];
		$this->draw_triangle($s, $ave);

		# 診断結果の各項目の点数
		$s = [$this->point[$subtitles[0]] / $this->full[$subtitles[0]],
              $this->point[$subtitles[1]] / $this->full[$subtitles[1]],
			  $this->point[$subtitles[2]] / $this->full[$subtitles[2]]];
		$this->draw_triangle($s, $you);

		# 凡例
		imageline($this->gd, 400, 220, 475, 220, $ave);
		imageline($this->gd, 400, 250, 475, 250, $you);
		$this->draw_title("平均", [500, 225], $ave);
		$this->draw_title("あなたの結果", [500, 255], $you);

		# 軸の見出し; 頂点とは微妙に座標を変える
		$this->draw_title($subtitles[0], [$this->p1[0]-7, $this->p1[1]-3], $black);
		$this->draw_title($subtitles[1], [$this->p2[0]-$fontsp, $this->p2[1]+10], $black);
		$this->draw_title($subtitles[2], [$this->p3[0]+21, $this->p3[1]+10], $black);

		// 画像出力
		header("Content-Type: image/png");
		imagepng($this->gd);
		imagedestroy($this->gd);
	}

    public function show_result($uuid) {
        // diagnoseテーブル
        $database = \Drupal::database();
        $query = $database->query("select message, average, stdev, total , deviation from {diagnose} where uuid=:uuid", [":uuid" => $uuid]);
        $pres = $query->fetchAssoc();
        $this->total = $pres["total"];
        $this->message = $pres["message"];
        $this->sd = $pres["stdev"];
        $this->average = $pres["average"];
        $this->deviation = $pres["deviation"];
        $query = $database->query("select item, point, average, full from {diagnose_item} where uuid=:uuid", [":uuid" => $uuid]);
        $this->point = array();
        while ($sres = $query->fetchAssoc()) {
            $this->point[$sres["item"]] = $sres["point"];
			$this->full[$sres["item"]] = $sres["full"];
			$this->itemave[$sres["item"]] = $sres["average"];
        }
        // 文字部分
        $this->classes = join(",", array_keys($this->point));

		$this->create_image();
    }
}


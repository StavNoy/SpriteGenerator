<?php
	/*
	 * DISCLAIMER
	 * 	hidden files not considered
	 * 	file names assumed to end with proper extension ('.png', '.css', etc.)
	 *  not accounting for multiple images with the same name
	 * 		if needed,
	 */

	/*
	 * Script entry point
	 * path to directory containing all PNGs always expected as last argument
	 * creates nd uses helper class 'CSS_Generator'
	*/

	$target = $argv[$argc-1];
	if (!is_dir($target))
	{
		echo "CSS Generator expects a valid folder path" . PHP_EOL;
		exit;
	}
	else
	{
		new CSS_Generator($target, $argv);
	}

	class CSS_Generator
	{
		private $opts = ['r' => FALSE, 'i' => 'sprite.png', 's' => 'style.css', 'p' => 0, 'o' => FALSE, 'c' => FALSE];
		private $imgC;
		private $cssData;
		private $masterImg;
		private $imagesData;

		/*
		 * Constructor serves as request executor
		*/
		public function __construct(string $target, array $args)
		{
			$this->set_opts($args);
			$this->setImages($target, $this->opts['o'] <= 0);
			$this->setMasterImg();
			$this->append_imgs();
			imagepng($this->masterImg, $this->opts["i"]); //NOTICE compression and file size options
			$this->gen_css();
		}

		/*
		 * reads specified options from passed arguments and sets to appropriate fields, with default values
		 * ASK unify in groups ? (no val, str val, num val)
		*/
		private function set_opts(array $args)
		{
			$args = array_slice($args, array_search(basename(__FILE__), $args)+1);
			array_pop($args);
			foreach ($args as $arg) {
				switch ($arg)
				{
					case '-r':
					case '-recursive':
						$this->opts['r'] = TRUE;
						break;
					case '-i':
					case '-output-image':
						$val = $this->getOptVal($arg, $args);
						$this->checkPath($val);
						$this->opts['i'] = $val;
						break;
					case '-s':
					case '-output-style':
						$val = $this->getOptVal($arg, $args);
						$this->checkPath($val);
						$this->opts['s'] = $val;
						break;
					case '-p':
					case '-padding':
						$this->opts['p'] = $this->getOptNum($arg, $args, 0);
						break;
					case '-o':
					case '-override-size':
						$this->opts['o'] = $this->getOptNum($arg, $args, 1);
						break;
					case '-c':
					case '-columns_number':
						$this->opts['c'] = $this->getOptNum($arg, $args, 1);
						break;
				}
			}
		}


		/*
		 * Options with values expect them at next index of arguments
		 * Not to be used for options with multiple or optional values
		*/
		private function getOptVal(string $arg, array $args)
		{
			$argI = array_search($arg, $args);
			if (count($args) <= $argI) {
				echo basename(__FILE__) . " : option requires an argument -- 'l'" . PHP_EOL;
				exit();
			}
			else
			{
				return $args[$argI + 1];
			}
		}

		private function checkPath(string $path)
		{
			if (file_exists($path) || !is_writable(dirname($path))) {
				echo basename(__FILE__) . " : cannot write to '$path' : file exists or permissions missing" . PHP_EOL;
				exit;
			}
		}

		/*
		 * getOptVal for numeric
		 * ASK maybe merge into getOptVal() with default valued param ? (int $min = FALSE ;...; if($min !== FALSE && $val > $min))
		 */
		private function getOptNum(string $arg, array $args, int $min)
		{
			$val = $this->getOptVal($arg, $args);
			if (is_numeric($val) && $val >= $min)
			{
				return $val;
			}
			else
			{
				echo basename(__FILE__) . " : $arg expects a numeric value of at least $min" . PHP_EOL;
				exit();
			}
		}

		//todo expand explanation comment
		// ask 2 ternaries instead of 1 if()
		/*
		 *
		 */
		private function setImages(string $target, bool $override = FALSE)
		{
			$pngs = $this->find_pngs_setImgC($target);
			$lastRow = ($this->opts['c'] > 0) ? ($this->imgC / $this->opts['c']) : 1;
			for ($row = 0; $row < $this->imgC && $row < $lastRow;)
			{
				foreach ($pngs as $src)
				{
					$img = imagecreatefrompng($src);
					$this->imagesData[$row][$src]['img'] = $img;
					if ($override)
					{
						$this->imagesData[$row][$src]['width'] = $this->opts['o'];
						$this->imagesData[$row][$src]['height'] = $this->opts['o'];
					}
					else
					{
						$this->imagesData[$row][$src]['width'] = imagesx($img);
						$this->imagesData[$row][$src]['height'] = imagesy($img);
					}
				}
			}
		}

		/*
		 * finds and returns all PNGs in $target, recursively if specified in args
		 * feedback and exit if non found
		 */
		private function find_pngs_setImgC(string $target)
		{
			if ($this->opts["r"]) {
				$pngs = $this->rec_glob_pngs($target);
			}
			else
			{
				$pngs = glob("$target/*.png");
			}
			$this->imgC = count($pngs);
			if ($this->imgC <= 0) {
				echo basename(__FILE__) . ' : no PNG files found' . PHP_EOL;
				exit();
			}
			else
			{
				return $pngs;
			}
		}

		/*
		 * recurse subdirectories for PNGs
		 */
		private function rec_glob_pngs(string $folder)
		{
			$sources = glob("$dir/*.png");
			$dirs = glob("$folder/*[^.]", GLOB_ONLYDIR);
			foreach ($dirs as $dir)
			{
				$sources = array_merge($sources, $this->rec_glob_pngs("$dir"));
			}
			return $sources;
		}

		/*
		 * calculate expected dimensions and create blank master image
		 */
		private function setMasterImg()
		{
			$w = 0;
			$h = 0;
			$col = $this->opts['c'];
			if ($col > 0)
			{
				$rows = array_chunk($this->imagesData, $col);
				foreach ($rows as $row)
				{
					$row_dims = $this->calc_dims($row);
					if ($w < $row_dims[0])
					{
						$w = $row_dims[0];
					}
					$h += $row_dims[1];
				}
			}
			else
			{
				$dims = $this->calc_dims($this->imagesData);
				$w = $dims[0];
				$h = $dims[1];
			}
			$this->masterImg = imagecreate($w,$h);
		}

		/*
		 * calculates overall size for each given row of images (accumulated width X largest height)
		 */
		private function calc_dims(array $imagesDataRow)
		{
			$w = ($this->imgC -1) * $this->opts['p'];
			$h = 0;
			foreach ($imagesDataRow as $imgData)
			{
				$w += $imgData['width'];
				if ($imgData['height'] > $h)
				{
					$h = $imgData['height'];
				}
			}
			return [$w,$h];
		}

		/*
		 * copies all images to $ masterImg and sets needed data for CSS
		 */
		private function append_imgs()
		{
			$destX = 0;
			$destY = 0;
			$col = 1;
			foreach ($this->imagesData as $imgName => $imgData)
			{
				imagecopy($this->masterImg, $imgData['img'], $destX, $destY, 0, 0, $imgData['width'], $imgData['height']);
				$fileName = pathinfo($imgName, PATHINFO_FILENAME);
				$this->cssData[$fileName] = ['x' => $destX, 'y' => $destY, 'width' => $imgData['width'], 'height' => $imgData['height']];
				if ($col == $this->opts['c'])
				{
					$col = 1;
					$destY += ; //todo make row height accessible
					$destX = 0;
				}
				else
				{
					$destX += $imgData['width'] + $this->opts['p'];
					$col++;
				}
			}
		}

		/*
		 * writes data from append_imgs() via $cssData to CSS file according to format, with a stamp
		 */
		private function gen_css()
		{
			$output = '/* Generated by ' . basename(__FILE__) . ' at ' . date("d-m-Y h:i a e") . ' */' . PHP_EOL . PHP_EOL;
			$output .= '.img {' . PHP_EOL .
							"\tdisplay: inline-block;" . PHP_EOL .
							"\tbackground: url('" . basename($this->opts['i']) . "') no-repeat;" . PHP_EOL .
							'}' . PHP_EOL . PHP_EOL;

			foreach ($this->cssData as $spriteName => $dimens) {
				$output .= ".img.img-$spriteName {" . PHP_EOL .
					"\tbackground-position: -" . $dimens['x'] . "px -" . $dimens['y'] . "px;" . PHP_EOL .
					"\twidth: " . $dimens['width'] . "px;" . PHP_EOL .
					"\theight: " . $dimens['height'] . "px;" . PHP_EOL .
					'}' . PHP_EOL . PHP_EOL;
			}
			file_put_contents($this->opts['s'],$output);
		}
	}
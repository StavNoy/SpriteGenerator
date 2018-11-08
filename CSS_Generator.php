<?php
	/*
	 * DISCLAIMER
	 * 	hidden files not considered
	 * 	file names assumed to end with proper extension ('.png', '.css', etc.)
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
		private $opts = ['r' => FALSE, 'i' => 'sprite.png', 's' => 'style.css', 'p' => 0, 'o' => FALSE];
		private $images;
		private $cssData = [];
		private $masterImg;

		/*
		 * Cunstructor serves as request executor
		*/
		public function __construct(string $target, array $args)
		{
			$this->set_opts($args);
			$this->images = glob("$target/*.png");
			if ($this->opts["r"]) {
				$this->rec_glob_pngs($target);
			}
			if (count($this->images) <= 0) {
				echo basename(__FILE__) . ' : no PNG files found' . PHP_EOL;
				exit();
			}
			else
			{
				$this->setMasterImg();
				$this->append_imgs();
				imagepng($this->masterImg, $this->opts["i"]); //NOTICE compression and file size options
				$this->gen_css();
			}
		}

		/*
		 * reads specified options from passed arguments and sets to appropriate fields, with default values
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
						$this->opts['o'] = $this->getOptNum($arg,$args, 1);
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

		/*
		 * recurse subdirectories for PNGs
		 */
		private function rec_glob_pngs(string $folder)
		{
			$dirs = glob("$folder/*[^.]", GLOB_ONLYDIR);
			foreach ($dirs as $dir)
			{
				$this->images = array_merge($this->images, glob("$dir/*.png"));
				$this->rec_glob_pngs("$dir");
			}
		}

		/*
		 * calculate expected dimensions and create blank master image
		 */
		private function setMasterImg ()
		{
			$imgC = count($this->images);
			$w = ($imgC-1) * $this->opts['p'];
			$h = 0;
			if ($this->opts['o'] > 0)
			{
				$w += $imgC * $this->opts['o'];
				$h = $this->opts['o'];
			}
			else
			{
				foreach ($this->images as $img)
				{
					$img = imagecreatefrompng($img);
					$w += imagesx($img);
					$imgY = imagesy($img);
					if ($imgY > $h)
					{
						$h = $imgY;
					}
				}
			}
			$this->masterImg = imagecreate($w,$h);
		}

		/*
		 * copies all images to $ masterImg and sets needed data for CSS
		 */
		private function append_imgs()
		{
			if ($this->opts['o'] > 0)
			{
				$this->append_imgs_override_size();
			}
			else
			{
				$images = $this->images;
				$firstImg = imagecreatefrompng($images[0]);
				$firstW = imagesx($firstImg);
				$firstH = imagesy($firstImg);
				imagecopy($this->masterImg, $firstImg, 0, 0, 0, 0, $firstW, $firstH);
				$this->cssData[pathinfo($images[0], PATHINFO_FILENAME)] = ['width' => $firstW, 'height' => $firstH];
				array_shift($images);
				$destX = $firstW + $this->opts['p'];
				foreach ($images as $imageName)
				{
					$image = imagecreatefrompng($imageName);
					$imgW = imagesx($image);
					$imgH = imagesy($image);
					imagecopy($this->masterImg, $image, $destX, 0, 0, 0, $imgW, $imgH);
					$destX += $imgW + $this->opts['p'];
					$this->cssData[pathinfo($imageName, PATHINFO_FILENAME)] = ['width' => $imgW, 'height' => $imgH];
				}
			}
		}

		/*
		 * append_imgs() for when size override specified
		*/
		private function append_imgs_override_size()
		{
			$images = $this->images;
			$firstImg = imagecreatefrompng($images[0]);
			$size = $this->opts['o'];
			imagecopy($this->masterImg, $firstImg, 0, 0, 0, 0, $size, $size);
			$this->cssData[pathinfo($images[0], PATHINFO_FILENAME)] = ['width' => $size, 'height' => $size];
			array_shift($images);
			$destX = $size + $this->opts['p'];
			foreach ($images as $imageName)
			{
				$image = imagecreatefrompng($imageName);
				imagecopy($this->masterImg, $image, $destX, 0, 0, 0, $size, $size);
				$destX += $size + $this->opts['p'];
				$this->cssData[pathinfo($imageName, PATHINFO_FILENAME)] = ['width' => $size, 'height' => $size];
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

			$lastX = 0;
			$format = ".img.img-%s {" . PHP_EOL .
				"\tbackground-position: -%dpx -0px;" . PHP_EOL .
				"\twidth: %dpx;" . PHP_EOL .
				"\theight: %dpx;" . PHP_EOL .
				'}' . PHP_EOL . PHP_EOL;
			foreach ($this->cssData as $spriteName => $dimens) {
				$output .= sprintf($format, $spriteName, $lastX, $dimens['width'], $dimens['height']);
				$lastX += $dimens['width'] + $this->opts['p'];
			}
			file_put_contents($this->opts['s'],$output);
		}
	}
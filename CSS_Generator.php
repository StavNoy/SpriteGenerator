<?php
	/*
	 * DISCLAIMER
	 * 	hidden files not considered
	 * 	output file extensions not added via code ('.png', '.css', etc.)
	 */

	/*
	 * Script entry point
	 * path to directory containing all PNGs always expected as last argument
	 * creates and uses helper class 'CSS_Generator'
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
		private $recurse = FALSE;
		private $spriteName = 'sprite.png';
		private $styleName = 'style.css';
		private $pad = 0;
		private $override = FALSE;
		private $col_limit = FALSE;
		private $imgDataRows;
		private $rowHeights;
		private $imgC;
		private $cssData;
		private $canvas;


		public function __construct(string $target, array $args)
		{
			$this->set_opts($args);
			$this->setImages($target);
			$this->setCanvas();
			$this->imgs_to_canvas();
			imagepng($this->canvas, $this->spriteName);
			$this->gen_css();
		}

		/*
		 * reads specified options from passed arguments and sets to appropriate fields, with default values
		*/
		private function set_opts(array $args)
		{
			$args = array_slice($args, array_search(basename(__FILE__), $args)+1);
			array_pop($args);
			foreach ($args as $arg) {
				if (($arg_val = $arg_val = $this->get_arg_equalVal($arg)) || ($arg_val = $this->get_arg_nextVal($arg, $args)))
				{
					$this->setOneOpt(...$arg_val);
				}
			}
		}

		private function setOneOpt($opt, $val)
		{
			switch ($opt)
			{
				case '-i':
				case '-output-image=':
					$this->spriteName = $this->checkOptPath($val);
					break;
				case '-s':
				case '-output-style=':
					$this->styleName = $this->checkOptPath($val);
					break;
				case '-p':
				case '-padding=':
					$this->pad = $this->checkOptNum($val, 0, $opt);
					break;
				case '-o':
				case '-override-size=':
					$this->override = $this->checkOptNum($val, 1, $opt);
					break;
				case '-c':
				case '-columns_number=':
					$this->col_limit = $this->checkOptNum($val, 1, $opt);
					break;
			}
		}

		/*
		 * check path value for option
		 */
		private function checkOptPath(string $path)
		{
			if (file_exists($path) || !is_writable(dirname($path))) {
				echo basename(__FILE__) . " : cannot write to '$path' : file exists or permissions missing" . PHP_EOL;
				exit;
			}
			else
			{
				return $path;
			}
		}

		/*
		 * check numeric value for option
		 */
		private function checkOptNum(string $val, int $min, string $arg)
		{
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
		 * short options with values expect them at next index of arguments
		 * Not to be used for options with multiple or optional values
		*/
		private function get_arg_nextVal(string $arg, array $args)
		{
			$argI = array_search($arg, $args);
			if (count($args) <= $argI+1) {
				return FALSE;
			}
			else
			{
				return [$arg, $args[$argI + 1]];
			}
		}

		/*
		 * long opts with vals will include them after '=' (i.e '-padding=0')
		*/
		private function get_arg_equalVal(string $arg)
		{
			if ($pos = strpos($arg, '='))
			{
				$opt = substr($arg, 0, $pos);
				$val = substr($arg, $pos);
				if (strlen($val) > 0)
				{
					return [$opt, $val];
				}
			}
			return FALSE;
		}

		/*
		 * initiates 2D array of all image data, where:
		 * 	1st level array : index => row
		 * 	2nd level array: 'name' => simple name
		 * 					'img' => created image,
		 * 					'width' and 'height' => final size		(may be overridden value)
		 */
		private function setImages(string $target)
		{
			$paths = $this->find_pngs_setImgC($target);
			$allData = [];
			foreach ($paths as $path)
			{
				$data['cssName'] = $spriteName = 'img_' . preg_replace(['/\//','/[^_a-z\d-]/'],['_', '-'], strtolower($path));
				$img = imagecreatefrompng($path);
				$data['img'] = $img;
				$data['width'] = $this->override ? $this->override : imagesx($img);
				$data['height'] = $this->override ? $this->override : imagesy($img);
				$allData[$path] = $data;
			}
			$this->imgDataRows = $this->col_limit ? array_chunk($allData, $this->col_limit) : [$allData];
		}

		/*
		 * finds and returns all PNG files' names in $target, recursively if specified in args
		 * feedback and exit if non found
		 */
		private function find_pngs_setImgC(string $target)
		{
			$pngs = [];
			$this->rec_add_pngs($target, $pngs);
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
		private function rec_add_pngs(string $folder, array &$pngs)
		{
			if ($dir = opendir($folder))
			{
				while ($file = readdir($dir))
				{
					if (mime_content_type("$folder/$file") == 'image/png')
					{
						$pngs[] = "$folder/$file";
					}
					elseif ($this->recurse && is_dir($file) && $file != '.' && $file != '..')
					{
						$this->rec_add_pngs("$folder/$file", $pngs);
					}
				}
				closedir($dir);
			}
		}

		/*
		 * calculate expected dimensions and create blank master image
		 * saves height for each row
		 */
		private function setCanvas()
		{
			$maxW = 0;
			$h = $this->pad * (count($this->imgDataRows) - 1);
			foreach ($this->imgDataRows as $i => $row)
			{
				$row_dims = $this->calc_row_dims($row);
				$this->rowHeights[$i] = $row_dims[1];
				$h += $row_dims[1];
				if ($maxW < $row_dims[0])
				{
					$maxW = $row_dims[0];
				}
			}
			$this->canvas = imagecreate($maxW,$h);
		}

		/*
		 * calculates overall size for each given row of imagesData (accumulated-width X max-height)
		 */
		private function calc_row_dims(array $imgDataRow)
		{
			$w = $this->pad * (count($imgDataRow) -1);
			$maxH = 0;
			foreach ($imgDataRow as $imgData)
			{
				$w += $imgData['width'];
				if ($imgData['height'] > $maxH)
				{
					$maxH = $imgData['height'];
				}
			}
			return [$w,$maxH];
		}

		/*
		 * copies all images to $ masterImg and sets needed data for CSS
		 */
		private function imgs_to_canvas()
		{
			$destX = 0;
			$destY = 0;
			foreach ($this->imgDataRows as $i => $row)
			{
				foreach ($row as $data)
				{
					imagecopy($this->canvas, $data['img'], $destX, $destY, 0, 0, $data['width'], $data['height']);
					$this->cssData[$data['cssName']] = ['x' => $destX, 'y' => $destY, 'width' => $data['width'], 'height' => $data['height']];
					$destX += $this->pad + $data['width'];
				}
				$destY += $this->pad + $this->rowHeights[$i];
				$destX = 0;
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
				"\tbackground: url('" . basename($this->spriteName) . "') no-repeat;" . PHP_EOL .
				'}' . PHP_EOL . PHP_EOL;

			foreach ($this->cssData as $spriteName => $dimens) {
				$output .= ".img.$spriteName {" . PHP_EOL .
					"\tbackground-position: -" . $dimens['x'] . "px -" . $dimens['y'] . "px;" . PHP_EOL .
					"\twidth: " . $dimens['width'] . "px;" . PHP_EOL .
					"\theight: " . $dimens['height'] . "px;" . PHP_EOL .
					'}' . PHP_EOL . PHP_EOL;
			}
			file_put_contents($this->styleName,$output);
		}
	}

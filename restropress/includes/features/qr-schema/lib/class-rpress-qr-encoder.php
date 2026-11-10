<?php
/**
 * Compact QR Code encoder (ISO/IEC 18004), byte mode, versions 1 to 40.
 *
 * Written for RestroPress and released under the same license as the plugin
 * (GPL-2.0-or-later). It has no WordPress dependencies so it can be unit
 * tested on its own (see tests/qr-encoder-standalone-test.php).
 *
 * Usage:
 *   $qr  = RPRESS_QR_Encoder::encode( 'https://example.com/menu/', 'M' );
 *   $svg = RPRESS_QR_Encoder::to_svg( $qr, array( 'label' => 'My Store' ) );
 *
 * @package RestroPress
 */

if ( class_exists( 'RPRESS_QR_Encoder' ) ) {
	return;
}

/**
 * QR Code encoder.
 */
class RPRESS_QR_Encoder {

	/**
	 * Error correction levels: index into the tables and the 2-bit format value.
	 *
	 * @var array
	 */
	private static $ecl = array(
		'L' => array( 0, 1 ),
		'M' => array( 1, 0 ),
		'Q' => array( 2, 3 ),
		'H' => array( 3, 2 ),
	);

	/**
	 * Error correction codewords per block, by level then version (index 0 unused).
	 *
	 * @var array
	 */
	private static $ecc_per_block = array(
		array( -1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
		array( -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28 ),
		array( -1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
		array( -1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30 ),
	);

	/**
	 * Number of error correction blocks, by level then version (index 0 unused).
	 *
	 * @var array
	 */
	private static $ecc_blocks = array(
		array( -1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25 ),
		array( -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49 ),
		array( -1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68 ),
		array( -1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81 ),
	);

	/**
	 * Working state for the symbol being built.
	 *
	 * @var int
	 */
	private $version;

	/**
	 * Side length in modules.
	 *
	 * @var int
	 */
	private $size;

	/**
	 * Module colors, [y][x], true = dark.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Function pattern flags, [y][x].
	 *
	 * @var array
	 */
	private $is_function = array();

	/**
	 * Encode text (UTF-8 bytes) as a QR Code.
	 *
	 * The smallest version that fits is used, and the error correction level is
	 * raised for free when the data still fits in that version.
	 *
	 * @param string $text   Data to encode.
	 * @param string $level  Minimum error correction level: L, M, Q or H.
	 * @param bool   $boost  Raise the level when it costs no extra size.
	 * @return array {
	 *     @type int    $version Version 1 to 40.
	 *     @type string $level   Error correction level used.
	 *     @type int    $mask    Mask pattern 0 to 7.
	 *     @type int    $size    Side length in modules.
	 *     @type array  $matrix  Rows of 0/1 ints, [y][x], 1 = dark.
	 * }
	 * @throws InvalidArgumentException When the text is too long for a QR Code.
	 */
	public static function encode( $text, $level = 'M', $boost = true ) {
		$text  = (string) $text;
		$level = strtoupper( (string) $level );
		if ( ! isset( self::$ecl[ $level ] ) ) {
			$level = 'M';
		}
		$bytes = '' === $text ? array() : array_values( unpack( 'C*', $text ) );
		$len   = count( $bytes );

		$version = 0;
		for ( $v = 1; $v <= 40; $v++ ) {
			if ( self::data_bits_needed( $len, $v ) <= self::data_capacity_bits( $v, $level ) ) {
				$version = $v;
				break;
			}
		}
		if ( ! $version ) {
			throw new InvalidArgumentException( 'Data too long for a QR Code.' );
		}
		if ( $boost ) {
			foreach ( array( 'M', 'Q', 'H' ) as $higher ) {
				if ( self::$ecl[ $higher ][0] > self::$ecl[ $level ][0] && self::data_bits_needed( $len, $version ) <= self::data_capacity_bits( $version, $higher ) ) {
					$level = $higher;
				}
			}
		}

		// Bit stream: byte mode indicator, character count, data.
		$bits = array();
		self::append_bits( $bits, 4, 4 );
		self::append_bits( $bits, $len, $version <= 9 ? 8 : 16 );
		foreach ( $bytes as $b ) {
			self::append_bits( $bits, $b, 8 );
		}
		$capacity = self::data_capacity_bits( $version, $level );
		self::append_bits( $bits, 0, min( 4, $capacity - count( $bits ) ) );
		self::append_bits( $bits, 0, ( 8 - count( $bits ) % 8 ) % 8 );
		for ( $pad = 0xEC; count( $bits ) < $capacity; $pad ^= 0xEC ^ 0x11 ) {
			self::append_bits( $bits, $pad, 8 );
		}
		$codewords = array();
		foreach ( array_chunk( $bits, 8 ) as $chunk ) {
			$codewords[] = bindec( implode( '', $chunk ) );
		}

		$qr = new self();
		return $qr->build( $version, $level, $codewords );
	}

	/**
	 * Build the symbol from data codewords.
	 *
	 * @param int    $version   Version.
	 * @param string $level     Error correction level.
	 * @param array  $codewords Data codewords.
	 * @return array
	 */
	private function build( $version, $level, $codewords ) {
		$this->version     = $version;
		$this->size        = $version * 4 + 17;
		$row               = array_fill( 0, $this->size, false );
		$this->modules     = array_fill( 0, $this->size, $row );
		$this->is_function = array_fill( 0, $this->size, $row );

		$this->draw_function_patterns( $level );
		$this->draw_codewords( self::add_ecc_and_interleave( $codewords, $version, $level ) );

		// Pick the mask with the lowest penalty.
		$best_mask    = 0;
		$best_penalty = PHP_INT_MAX;
		for ( $mask = 0; $mask < 8; $mask++ ) {
			$this->apply_mask( $mask );
			$this->draw_format_bits( $level, $mask );
			$penalty = $this->penalty_score();
			if ( $penalty < $best_penalty ) {
				$best_mask    = $mask;
				$best_penalty = $penalty;
			}
			$this->apply_mask( $mask ); // XOR again to undo.
		}
		$this->apply_mask( $best_mask );
		$this->draw_format_bits( $level, $best_mask );

		$matrix = array();
		foreach ( $this->modules as $y => $cells ) {
			$matrix[ $y ] = array_map( 'intval', $cells );
		}
		return array(
			'version' => $version,
			'level'   => $level,
			'mask'    => $best_mask,
			'size'    => $this->size,
			'matrix'  => $matrix,
		);
	}

	/**
	 * Bits needed for a byte-mode segment of $len bytes in $version.
	 *
	 * @param int $len     Byte count.
	 * @param int $version Version.
	 * @return int
	 */
	private static function data_bits_needed( $len, $version ) {
		$count_bits = $version <= 9 ? 8 : 16;
		if ( $len >= ( 1 << $count_bits ) ) {
			return PHP_INT_MAX;
		}
		return 4 + $count_bits + 8 * $len;
	}

	/**
	 * Data capacity in bits for a version and level.
	 *
	 * @param int    $version Version.
	 * @param string $level   Level.
	 * @return int
	 */
	private static function data_capacity_bits( $version, $level ) {
		$e = self::$ecl[ $level ][0];
		return ( intdiv( self::num_raw_data_modules( $version ), 8 ) - self::$ecc_per_block[ $e ][ $version ] * self::$ecc_blocks[ $e ][ $version ] ) * 8;
	}

	/**
	 * Data modules available after all function patterns (includes remainder bits).
	 *
	 * @param int $ver Version.
	 * @return int
	 */
	private static function num_raw_data_modules( $ver ) {
		$result = ( 16 * $ver + 128 ) * $ver + 64;
		if ( $ver >= 2 ) {
			$num_align = intdiv( $ver, 7 ) + 2;
			$result   -= ( 25 * $num_align - 10 ) * $num_align - 55;
			if ( $ver >= 7 ) {
				$result -= 36;
			}
		}
		return $result;
	}

	/**
	 * Append $length low bits of $value, most significant first.
	 *
	 * @param array $bits   Bit buffer.
	 * @param int   $value  Value.
	 * @param int   $length Bit count.
	 * @return void
	 */
	private static function append_bits( &$bits, $value, $length ) {
		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	/**
	 * Split data into blocks, add Reed-Solomon codewords and interleave.
	 *
	 * @param array  $data    Data codewords.
	 * @param int    $version Version.
	 * @param string $level   Level.
	 * @return array
	 */
	private static function add_ecc_and_interleave( $data, $version, $level ) {
		$e            = self::$ecl[ $level ][0];
		$num_blocks   = self::$ecc_blocks[ $e ][ $version ];
		$ecc_len      = self::$ecc_per_block[ $e ][ $version ];
		$raw          = intdiv( self::num_raw_data_modules( $version ), 8 );
		$num_short    = $num_blocks - $raw % $num_blocks;
		$short_len    = intdiv( $raw, $num_blocks );
		$divisor      = self::rs_divisor( $ecc_len );
		$blocks       = array();
		$k            = 0;
		for ( $i = 0; $i < $num_blocks; $i++ ) {
			$len = $short_len - $ecc_len + ( $i < $num_short ? 0 : 1 );
			$dat = array_slice( $data, $k, $len );
			$k  += $len;
			$ecc = self::rs_remainder( $dat, $divisor );
			if ( $i < $num_short ) {
				$dat[] = 0; // Placeholder so all blocks line up; skipped below.
			}
			$blocks[] = array_merge( $dat, $ecc );
		}
		$result = array();
		$width  = count( $blocks[0] );
		for ( $i = 0; $i < $width; $i++ ) {
			foreach ( $blocks as $j => $block ) {
				if ( $i !== $short_len - $ecc_len || $j >= $num_short ) {
					$result[] = $block[ $i ];
				}
			}
		}
		return $result;
	}

	/**
	 * Reed-Solomon generator polynomial coefficients for $degree.
	 *
	 * @param int $degree Degree.
	 * @return array
	 */
	private static function rs_divisor( $degree ) {
		$result               = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root                 = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::gf_mul( $result[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}
			$root = self::gf_mul( $root, 0x02 );
		}
		return $result;
	}

	/**
	 * Reed-Solomon remainder of $data divided by $divisor.
	 *
	 * @param array $data    Codewords.
	 * @param array $divisor Generator.
	 * @return array
	 */
	private static function rs_remainder( $data, $divisor ) {
		$n      = count( $divisor );
		$result = array_fill( 0, $n, 0 );
		foreach ( $data as $b ) {
			$factor   = $b ^ array_shift( $result );
			$result[] = 0;
			for ( $i = 0; $i < $n; $i++ ) {
				$result[ $i ] ^= self::gf_mul( $divisor[ $i ], $factor );
			}
		}
		return $result;
	}

	/**
	 * Multiply in GF(2^8) modulo x^8 + x^4 + x^3 + x^2 + 1.
	 *
	 * @param int $x Factor.
	 * @param int $y Factor.
	 * @return int
	 */
	private static function gf_mul( $x, $y ) {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z;
	}

	/**
	 * Set a function module.
	 *
	 * @param int  $x    Column.
	 * @param int  $y    Row.
	 * @param bool $dark Color.
	 * @return void
	 */
	private function set_function( $x, $y, $dark ) {
		$this->modules[ $y ][ $x ]     = (bool) $dark;
		$this->is_function[ $y ][ $x ] = true;
	}

	/**
	 * Draw finder, timing, alignment, format and version patterns.
	 *
	 * @param string $level Level.
	 * @return void
	 */
	private function draw_function_patterns( $level ) {
		$size = $this->size;
		for ( $i = 0; $i < $size; $i++ ) {
			$this->set_function( 6, $i, 0 === $i % 2 );
			$this->set_function( $i, 6, 0 === $i % 2 );
		}
		$this->draw_finder( 3, 3 );
		$this->draw_finder( $size - 4, 3 );
		$this->draw_finder( 3, $size - 4 );

		$align = $this->alignment_positions();
		$last  = count( $align ) - 1;
		foreach ( $align as $i => $ax ) {
			foreach ( $align as $j => $ay ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $j === $last ) || ( $i === $last && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$this->set_function( $ax + $dx, $ay + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
					}
				}
			}
		}

		$this->draw_format_bits( $level, 0 ); // Reserve the area; redrawn per mask.

		if ( $this->version >= 7 ) {
			$rem = $this->version;
			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}
			$bits = ( $this->version << 12 ) | $rem;
			for ( $i = 0; $i < 18; $i++ ) {
				$bit = ( $bits >> $i ) & 1;
				$a   = $size - 11 + $i % 3;
				$b   = intdiv( $i, 3 );
				$this->set_function( $a, $b, $bit );
				$this->set_function( $b, $a, $bit );
			}
		}
	}

	/**
	 * Draw a finder pattern with separator centered at ($x, $y).
	 *
	 * @param int $x Column.
	 * @param int $y Row.
	 * @return void
	 */
	private function draw_finder( $x, $y ) {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$xx = $x + $dx;
				$yy = $y + $dy;
				if ( $xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size ) {
					$dist = max( abs( $dx ), abs( $dy ) );
					$this->set_function( $xx, $yy, 2 !== $dist && 4 !== $dist );
				}
			}
		}
	}

	/**
	 * Alignment pattern center coordinates for this version.
	 *
	 * @return array
	 */
	private function alignment_positions() {
		if ( 1 === $this->version ) {
			return array();
		}
		$num_align = intdiv( $this->version, 7 ) + 2;
		$step      = intdiv( $this->version * 8 + $num_align * 3 + 5, $num_align * 4 - 4 ) * 2;
		$result    = array();
		for ( $i = 0, $pos = $this->size - 7; $i < $num_align - 1; $i++, $pos -= $step ) {
			array_unshift( $result, $pos );
		}
		array_unshift( $result, 6 );
		return $result;
	}

	/**
	 * Draw both copies of the 15-bit format information.
	 *
	 * @param string $level Level.
	 * @param int    $mask  Mask.
	 * @return void
	 */
	private function draw_format_bits( $level, $mask ) {
		$data = ( self::$ecl[ $level ][1] << 3 ) | $mask;
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$size = $this->size;

		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set_function( 8, $i, ( $bits >> $i ) & 1 );
		}
		$this->set_function( 8, 7, ( $bits >> 6 ) & 1 );
		$this->set_function( 8, 8, ( $bits >> 7 ) & 1 );
		$this->set_function( 7, 8, ( $bits >> 8 ) & 1 );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set_function( 14 - $i, 8, ( $bits >> $i ) & 1 );
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function( $size - 1 - $i, 8, ( $bits >> $i ) & 1 );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set_function( 8, $size - 15 + $i, ( $bits >> $i ) & 1 );
		}
		$this->set_function( 8, $size - 8, true ); // Always-dark module.
	}

	/**
	 * Place codewords in the zigzag order.
	 *
	 * @param array $data Final codewords.
	 * @return void
	 */
	private function draw_codewords( $data ) {
		$total = count( $data ) * 8;
		$i     = 0;
		for ( $right = $this->size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			$upward = 0 === ( ( $right + 1 ) & 2 );
			for ( $vert = 0; $vert < $this->size; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x = $right - $j;
					$y = $upward ? $this->size - 1 - $vert : $vert;
					if ( ! $this->is_function[ $y ][ $x ] && $i < $total ) {
						$this->modules[ $y ][ $x ] = (bool) ( ( $data[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
						$i++;
					}
				}
			}
		}
	}

	/**
	 * XOR a mask pattern over the data modules (applying twice undoes it).
	 *
	 * @param int $mask Mask 0 to 7.
	 * @return void
	 */
	private function apply_mask( $mask ) {
		for ( $y = 0; $y < $this->size; $y++ ) {
			for ( $x = 0; $x < $this->size; $x++ ) {
				if ( $this->is_function[ $y ][ $x ] ) {
					continue;
				}
				switch ( $mask ) {
					case 0:
						$invert = 0 === ( $x + $y ) % 2;
						break;
					case 1:
						$invert = 0 === $y % 2;
						break;
					case 2:
						$invert = 0 === $x % 3;
						break;
					case 3:
						$invert = 0 === ( $x + $y ) % 3;
						break;
					case 4:
						$invert = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;
						break;
					case 5:
						$invert = 0 === $x * $y % 2 + $x * $y % 3;
						break;
					case 6:
						$invert = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2;
						break;
					default:
						$invert = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $invert ) {
					$this->modules[ $y ][ $x ] = ! $this->modules[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Mask penalty score (rules N1 to N4 of the spec).
	 *
	 * @return int
	 */
	private function penalty_score() {
		$size    = $this->size;
		$penalty = 0;
		$dark    = 0;
		$lines   = array();
		for ( $y = 0; $y < $size; $y++ ) {
			$row = '';
			$col = '';
			for ( $x = 0; $x < $size; $x++ ) {
				$row .= $this->modules[ $y ][ $x ] ? '1' : '0';
				$col .= $this->modules[ $x ][ $y ] ? '1' : '0';
			}
			$lines[] = $row;
			$lines[] = $col;
			$dark   += substr_count( $row, '1' );
		}
		foreach ( $lines as $line ) {
			// N1: runs of five or more same-colored modules.
			if ( preg_match_all( '/0{5,}|1{5,}/', $line, $runs ) ) {
				foreach ( $runs[0] as $run ) {
					$penalty += 3 + strlen( $run ) - 5;
				}
			}
			// N3: finder-like 1:1:3:1:1 patterns next to four light modules.
			$padded   = '0000' . $line . '0000';
			$penalty += 40 * ( preg_match_all( '/(?=00001011101)/', $padded ) + preg_match_all( '/(?=10111010000)/', $padded ) );
		}
		// N2: 2x2 blocks of one color.
		for ( $y = 0; $y < $size - 1; $y++ ) {
			for ( $x = 0; $x < $size - 1; $x++ ) {
				$c = $this->modules[ $y ][ $x ];
				if ( $c === $this->modules[ $y ][ $x + 1 ] && $c === $this->modules[ $y + 1 ][ $x ] && $c === $this->modules[ $y + 1 ][ $x + 1 ] ) {
					$penalty += 3;
				}
			}
		}
		// N4: dark/light balance.
		$total    = $size * $size;
		$k        = (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1;
		$penalty += max( 0, $k ) * 10;
		return $penalty;
	}

	/**
	 * Render an encoded symbol as an SVG document.
	 *
	 * @param array $qr   Result of encode().
	 * @param array $args {
	 *     @type int    $module Module size in user units. Default 10.
	 *     @type int    $margin Quiet zone in modules. Default 4.
	 *     @type string $label  First caption line under the code (e.g. store name).
	 *     @type string $sub    Second caption line (e.g. table label).
	 *     @type string $fg     Dark color. Default #000000.
	 *     @type string $bg     Light color. Default #ffffff.
	 *     @type string $title  Accessible title.
	 * }
	 * @return string
	 */
	public static function to_svg( $qr, $args = array() ) {
		$args   = array_merge(
			array(
				'module' => 10,
				'margin' => 4,
				'label'  => '',
				'sub'    => '',
				'fg'     => '#000000',
				'bg'     => '#ffffff',
				'title'  => '',
			),
			$args
		);
		$m      = max( 1, (int) $args['module'] );
		$margin = max( 0, (int) $args['margin'] );
		$size   = (int) $qr['size'];
		$side   = ( $size + 2 * $margin ) * $m;
		$fg     = self::color( $args['fg'], '#000000' );
		$bg     = self::color( $args['bg'], '#ffffff' );

		$lines = array();
		foreach ( array( 'label' => 1.0, 'sub' => 0.8 ) as $key => $scale ) {
			if ( '' !== trim( (string) $args[ $key ] ) ) {
				$lines[] = array( trim( (string) $args[ $key ] ), $scale );
			}
		}
		$font   = max( 12, (int) round( $side / 14 ) );
		$height = $side + ( $lines ? (int) round( $font * 0.4 ) : 0 );
		$texts  = '';
		foreach ( $lines as $line ) {
			$fs      = (int) round( $font * $line[1] );
			$height += (int) round( $fs * 1.4 );
			$texts  .= sprintf(
				'<text x="%1$d" y="%2$d" font-family="Helvetica, Arial, sans-serif" font-size="%3$d" font-weight="%4$s" text-anchor="middle" fill="%5$s">%6$s</text>',
				(int) ( $side / 2 ),
				$height - (int) round( $fs * 0.4 ),
				$fs,
				1.0 === $line[1] ? 'bold' : 'normal',
				$fg,
				self::xml( $line[0] )
			);
		}
		if ( $lines ) {
			$height += (int) round( $font * 0.6 );
		}

		$path = '';
		foreach ( $qr['matrix'] as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$path .= 'M' . ( $x + $margin ) . ',' . ( $y + $margin ) . 'h1v1h-1z';
				}
			}
		}

		$svg  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$svg .= sprintf( '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img">', $side, $height );
		if ( '' !== (string) $args['title'] ) {
			$svg .= '<title>' . self::xml( $args['title'] ) . '</title>';
		}
		$svg .= sprintf( '<rect width="%d" height="%d" fill="%s"/>', $side, $height, $bg );
		$svg .= sprintf( '<path transform="scale(%d)" shape-rendering="crispEdges" fill="%s" d="%s"/>', $m, $fg, $path );
		$svg .= $texts . '</svg>' . "\n";
		return $svg;
	}

	/**
	 * Render an encoded symbol as PNG bytes with GD, or null without GD.
	 *
	 * Captions use GD's built-in font, which only covers ASCII; other
	 * characters are dropped (the SVG keeps the full text).
	 *
	 * @param array $qr   Result of encode().
	 * @param array $args Same keys as to_svg(); 'module' is pixels per module.
	 * @return string|null
	 */
	public static function to_png( $qr, $args = array() ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
			return null;
		}
		$args   = array_merge(
			array(
				'module' => 10,
				'margin' => 4,
				'label'  => '',
				'sub'    => '',
				'fg'     => '#000000',
				'bg'     => '#ffffff',
			),
			$args
		);
		$m      = max( 1, (int) $args['module'] );
		$margin = max( 0, (int) $args['margin'] );
		$size   = (int) $qr['size'];
		$side   = ( $size + 2 * $margin ) * $m;

		$lines = array();
		foreach ( array( 'label' => 5, 'sub' => 4 ) as $key => $gd_font ) {
			$text = trim( (string) preg_replace( '/[^\x20-\x7E]/', '', (string) $args[ $key ] ) );
			if ( '' !== $text ) {
				$lines[] = array( $text, $gd_font );
			}
		}
		// Built-in GD fonts are tiny; draw them on a strip and scale it up.
		$scale  = max( 2, (int) floor( $side / 200 ) );
		$lift   = (int) ( $margin * $m / 2 ); // Tuck captions into the bottom quiet zone.
		$strips = array();
		$extra  = 0;
		foreach ( $lines as $line ) {
			$cw    = imagefontwidth( $line[1] );
			$ch    = imagefontheight( $line[1] );
			$max   = max( 1, (int) floor( $side / ( $cw * $scale ) ) - 2 );
			$text  = strlen( $line[0] ) > $max ? substr( $line[0], 0, max( 1, $max - 3 ) ) . '...' : $line[0];
			$w     = strlen( $text ) * $cw;
			$strip = array( $text, $line[1], $w, $ch );
			$strips[] = $strip;
			$extra   += (int) ( $ch * $scale * 1.5 );
		}
		if ( $strips ) {
			$extra = max( 0, $extra - $lift ) + $m * 2;
		}

		$img = imagecreatetruecolor( $side, $side + $extra );
		list( $fr, $fgc, $fb ) = self::rgb( self::color( $args['fg'], '#000000' ) );
		list( $br, $bgc, $bb ) = self::rgb( self::color( $args['bg'], '#ffffff' ) );
		$fg = imagecolorallocate( $img, $fr, $fgc, $fb );
		$bg = imagecolorallocate( $img, $br, $bgc, $bb );
		imagefilledrectangle( $img, 0, 0, $side - 1, $side + $extra - 1, $bg );
		foreach ( $qr['matrix'] as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$px = ( $x + $margin ) * $m;
					$py = ( $y + $margin ) * $m;
					imagefilledrectangle( $img, $px, $py, $px + $m - 1, $py + $m - 1, $fg );
				}
			}
		}
		$top = $side - $lift;
		foreach ( $strips as $strip ) {
			list( $text, $gd_font, $w, $h ) = $strip;
			$tmp = imagecreatetruecolor( $w, $h );
			$tbg = imagecolorallocate( $tmp, $br, $bgc, $bb );
			$tfg = imagecolorallocate( $tmp, $fr, $fgc, $fb );
			imagefilledrectangle( $tmp, 0, 0, $w - 1, $h - 1, $tbg );
			imagestring( $tmp, $gd_font, 0, 0, $text, $tfg );
			$dw = $w * $scale;
			$dh = $h * $scale;
			imagecopyresized( $img, $tmp, (int) ( ( $side - $dw ) / 2 ), $top, 0, 0, $dw, $dh, $w, $h );
			self::free( $tmp );
			$top += (int) ( $h * $scale * 1.5 );
		}
		ob_start();
		imagepng( $img );
		$png = ob_get_clean();
		self::free( $img );
		return $png;
	}

	/**
	 * Free a GD image on PHP 7 (a no-op, and deprecated, from PHP 8 on).
	 *
	 * @param resource|GdImage $img Image.
	 * @return void
	 */
	private static function free( $img ) {
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $img );
		}
	}

	/**
	 * Validate a #rrggbb color.
	 *
	 * @param string $color    Color.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private static function color( $color, $fallback ) {
		return preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $color ) ? strtolower( $color ) : $fallback;
	}

	/**
	 * Split #rrggbb into ints.
	 *
	 * @param string $hex Color.
	 * @return array
	 */
	private static function rgb( $hex ) {
		return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
	}

	/**
	 * Escape text for XML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function xml( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}

<?php

/**
 * Example:
 *   $metar = new metar('KARB 152053Z 10007KT 6SM -RA OVC019 04/01 A3006 RMK AO2 SLP187 P0000 60000 T00390006 56024');
 *   echo $metar->raw_text . ': ' . $metar->flight_category . "\n";
 */

class metar
{
	public $raw_text;
	public $station_id;
	public $observation_time;
	public $visibility_statute_mi;
	public $cloud_ceiling;
	public $flight_category;

	private $metarCodePatterns = array(
		"visibility_statute_mi"   => "(\d+)SM",
		"clouds"                  => "SKC|CLR|NSC|((FEW|SCT|BKN|OVC)(\d{3}))",
	);

	function __construct($metar)
	{
		$this->raw_text = $metar;

		// obtain the station code and time
		if (preg_match('/^(?<station_id>[[:upper:]]{4}) (?<observation_time>(\d{2})?(\d{4})Z) /', $metar, $matches) === false)
		{
			throw new Exception("Unable to parse station id and observation time out of metar: '$metar'");
		}

		$this->station_id = $matches['station_id'];
		$this->observation_time = $matches['observation_time'];

		// remove the station code and time from the metar
		$metar = str_replace($matches[0], '', $metar);

		// parse remaining metar codes
		foreach ($this->metarCodePatterns as $name => $pattern)
		{
			if (($found = preg_match_all('/\s?' . $pattern . '\s?/', $metar, $matches, PREG_SET_ORDER)) == true)
			{
				switch ($name)
				{
					case 'visibility_statute_mi':
						$this->visibility_statute_mi = $matches[0][1];
						break;
					case 'clouds':
						foreach ($matches as $match)
						{
							// calculate the cloud ceiling
							if (($match[2] == 'BKN' || $match[2] == 'OVC') && ($this->cloud_ceiling === null || $this->cloud_ceiling > $match[3]))
							{
								$this->cloud_ceiling = $match[3];
							}
						}
				}
			}
		}

		// calculate the flight category - based on information from http://aviationweather.gov/static/info/afc.html
		if ($this->visibility_statute_mi > 5 && ($this->cloud_ceiling === null | $this->cloud_ceiling > 30))
		{
			$this->flight_category = 'VFR';
		}
		elseif ($this->visibility_statute_mi >= 3 && $this->cloud_ceiling >= 10)
		{
			$this->flight_category = 'MVFR';
		}
		elseif ($this->visibility_statute_mi >= 1 && $this->cloud_ceiling >= 5)
		{
			$this->flight_category = 'IFR';
		}
		else
		{
			$this->flight_category = 'LIFR';
		}
	}
}

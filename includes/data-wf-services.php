<?php

/**
 * USPS Services and subservices
 */
return array(
	// Domestic
	'D_FIRST_CLASS' => array(
		// Name of the service shown to the user
		'name'  => 'First-Class Mail&#0174;',

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			"0"  => "First-Class Mail&#0174; Parcel",
			'12' => "First-Class&#8482; Postcard Stamped",
			'15' => "First-Class&#8482; Large Postcards",
			'19' => "First-Class&#8482; Keys and IDs",
			'61' => "First-Class&#8482; Package Service",
			'53' => "First-Class&#8482; Package Service Hold For Pickup",
			'78' => "First-Class Mail&#0174; Metered Letter"
		)
	),
	'D_EXPRESS_MAIL' => array(
		// Name of the service shown to the user
		'name'  => 'Priority Mail Express&#8482;',

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			'2'  => "Priority Mail Express&#8482; Hold for Pickup",
			'3'  => "Priority Mail Express&#8482; PO to Address",
			'23' => "Priority Mail Express&#8482; Sunday/Holiday",
		)
	),
	'D_STANDARD_POST' => array(
		// Name of the service shown to the user
		'name'  => 'Standard Post&#8482;',

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			'4'  => "Standard Post&#8482;"
		)
	),
	'D_BPM' => array(
		// Name of the service shown to the user
		'name'  => 'Bound Printed Matter',

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			'5'  => "Bound Printed Matter"
		)
	),
	'D_MEDIA_MAIL' => array(
		// Name of the service shown to the user
		'name'  => 'Media Mail Parcel',

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			'6'  => "Media Mail Parcel"
		)
	),
	'D_LIBRARY_MAIL' => array(
		// Name of the service shown to the user
		'name'  => "Library Mail Parcel",

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			'7'  => "Library Mail Parcel"
		)
	),
	'D_PRIORITY_MAIL' => array(
		// Name of the service shown to the user
		'name'  => "Priority Mail&#0174;",

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			"1"  => "Priority Mail&#0174;",
			"18" => "Priority Mail&#0174; Keys and IDs",
			"47" => "Priority Mail&#0174; Regional Rate Box A",
			"49" => "Priority Mail&#0174; Regional Rate Box B",
			"58" => "Priority Mail&#0174; Regional Rate Box C",
		)
	),

	// International
	'I_EXPRESS_MAIL' => array(
		// Name of the service shown to the user
		'name'  => "Priority Mail Express International&#8482;",

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			"1"  => "Priority Mail Express International&#8482;",
		)
	),
	'I_PRIORITY_MAIL' => array(
		// Name of the service shown to the user
		'name'  => "Priority Mail International&#0174;",

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			"2"  => "Priority Mail International&#0174;",
		)
	),
	'I_GLOBAL_EXPRESS' => array(
		// Name of the service shown to the user
		'name'  => "Global Express Guaranteed&#0174;",

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			"4"  => "Global Express Guaranteed&#0174;",
			"12"  => "Global Express Guaranteed&#0174; Envelope",
		)
	),
	'I_FIRST_CLASS' => array(
		// Name of the service shown to the user
		'name'  => "First Class Package Service&#8482; International",

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			"13"  => "First Class Package Service&#8482; International Letters",
			"14"  => "First Class Package Service&#8482; International Large Envelope",
			"15"  => "First Class Package Service&#8482; International Parcel"
		)
	),
	'I_POSTCARDS' => array(
		// Name of the service shown to the user
		'name'  => "International Postcards",

		// Services which costs are merged if returned (cheapest is used). This gives us the best possible rate.
		'services' => array(
			"21"  => "International Postcards"
		)
	)
);
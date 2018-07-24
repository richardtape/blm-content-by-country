# Usage

if ( BLM_Content_By_Country::is_user_in( 'US' ) ) {
    // Do something if a user is in The United States
}

if ( BLM_Content_By_Country::is_user_in( 'CA' ) ) {
    // Do something if a user is in Canada
}

// Get the 2-letter ISO country code for the current user
$country_code = BLM_Content_By_Country::get_country_code_of_visitor();

# Overrides

You can set 'BLM_CBC_IP_OVERRIDE' to override the IP address for ALL visitors. i.e. in wp-config.php

define( 'BLM_CBC_IP_OVERRIDE', '70.69.6.208' ); // Canada

or

define( 'BLM_CBC_IP_OVERRIDE', '192.121.82.2' ); // USA

You can also use a $_GET parameter of forcecountry to enable you to force a specific country.

i.e. if the URL is http://hippiesnacks.local/ you can go to

http://hippiesnacks.local/?forcecountry=CA

To fake a visit from a user from Canada or

http://hippiesnacks.local/?forcecountry=US

To fake a visit from a user from the US. Any 2-letter country code will work.

# Notes

A cookie (which lasts for 24 hours) is set and checked for whenever a user requests content
checked for with this API. So there's not an external request for every single pageload. The
cookie name is 'blm_country_code'
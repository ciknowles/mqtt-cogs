# mqtt-cogs
Wordpress mqtt plugin

## Synopsis

Wordpress MQTT plugin.

MqttCogs maintains a persistent (well semi persistent) connection to an Mqtt Broker. When a message arrives on a subscribed topic, it persists it to a custom database table in wordpress.

So that you can ‘see’ the data in your blog it provides a number shortcodes for visualizing your data. Visualization uses Google Visualization

## Code Example

[ mqttcogs_drawgoogle charttype=”LineChart” ]
   [ mqttcogs_data limit=”40″ topics=”mysensors_out/100/1/1/0/0″ ]
[ /mqttcogs_drawgoogle ]

## Motivation

I’ve been mucking around with IoT (internet of things) projects for over a year now. I started with Arduino & the MySensors project. Annoyingly, it really isn’t as easy as you think!

I didn’t want to rent a server and then have to install IOT Controller software. What I do have though is a wordpress blog. This costs me $1.50 per month to rent. So I started wondering about getting MQTT data into the WordPress database and visualizing the data on my blog.

## Installation

[TO DO]

## Shortcode Reference

http://mqttcogs.sailresults.org/shortcodes/

## Tests

There are no tests.

## Contributors

Me at the moment but let me know if you want to help out. See http://mqttcogs.sailresults.org/about/ for contact information

## License

A short snippet describing the license (MIT, Apache, etc.)

# Instructions

1) This assumes you're running a fresh install of Ubuntu 16, so set up an Ubuntu 16 server (or equiv)
2) You need a MySQL server. I personally have a Amazon Aurora instance for this purpose, but you could also run it on the same server as you're hosting this code. Once the instance is created, run the migrate.sql file on it to create the database and required table structure.
2) Run setup.sh on the Ubuntu server to install the required dependencies
3) Move the contets of this folder folder into /var/www
4) Ensure your Apache2 [Apache2 should have been installed by default on Ubuntu 16] virtualhost is pointing at /var/www/html (this is important to keep the config.php directory out of the public folder)
5) Update /var/www/config.php with the correct variables for your MySQL instance and generate a random key for your auth_key variable
6) You should be able to test your server is setup correctly by going to http://YOURSERVERNAME/index?healthcheck=true and getting a HTTP 200 response. The page should output a sensible error you can use for debugging in the case of something not being setup correctly.

NB. The auth_key can be anything, it just has to match whatever you use in the mysql-event-logger app settings. I'd recomend making it something long, 16-32 characters. Keep in mind that unless you're running the server over HTTPS that it may be public, so keep it to something you don't mind possibly being discovered by someone else.

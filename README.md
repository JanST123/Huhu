# What is Huhu?

## What it aims to be

Huhu is a messenger app, running on many mobile devices (via Phonegap) and in webbrowsers. It aims to be simple but also secure
and therefore uses a private-/public key mechanism for transfering messages between the server and the app.

## What it actually is

All the above but in a very early state, not very stable and proofed. A pre-alpha version with some bugs in it and still
missing a lot of features.


# What will I find in this repository?

In this repository you'll find the Huhu-**Backend**, which is written in PHP and Zend-Framework 1.10 and uses memcache as primary datastore and a 
mySQL-Database as a permanent backup-datastore.

**Please note that there currently exists no guides, except for the comments in the code. But I'm working on it, and you'll 
find __a well documented API and some guides how to set up__ here soon.**

You'll find a guide, how to set up the backend on your dev-machine, here later. If you want to use the backend on my server 
(i.e. if you whish to develop the frontend), you can use it on http://we-hu.hu/api/.
You will also find a complete API-Documentation here later.

The **Frontend** is located in the "Huhu-Frontend" repository here on github.

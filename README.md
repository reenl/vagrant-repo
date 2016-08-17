# Vagrant repository

This project aims to create a minimal vagrant repository to host private vagrant boxes.

## How to use

The project is build based on [silex](http://silex.sensiolabs.org/), so it shouldn't be hard to install.

You should set the `SYMFONY_ENV` environment variable to `prod`. Unless you want to do some development on this project, then `dev` will do a better job, because `app_dev.php` will be available.

Don't forget about `composer install`.

## Does it scale

NO, scandir and generating everything at runtime isn't a good idea.

## Do you accept pull requests

Yes, please create an issue first if you don't want to waste your time.

## How do I create a box?

The easiest way is to use an existing base box, ubuntu for example, modify it to your needs and run:

```
vagrant package --output VERSION.box
```

https://scotch.io/tutorials/how-to-create-a-vagrant-base-box-from-an-existing-one

## Got some other resources?

Yes I do!

https://www.vagrantup.com/docs/boxes.html
https://github.com/hollodotme/Helpers/blob/master/Tutorials/vagrant/self-hosted-vagrant-boxes-with-versioning.md

## Getting in contact

- [@justreenl](https://twitter.com/justreenl)


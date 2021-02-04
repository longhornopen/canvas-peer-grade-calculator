# canvas-peer-grade-calculator
A tool for calculating grades from Canvas peer reviews.  For a Canvas peer-graded assignment, you can easily view individual grades and comments, calculate average scores, identify outliers, export grades to Excel and import average grades to the Canvas grade book.

## Installation
* Clone or download this repo.
* Install PHP (7.3 or higher)
* Install Composer (https://getcomposer.org)
* `cd` into the 'web' folder in this repo.
* `cp .env.example .env` to create a new env file from the provided example.
* `composer install` (to install PHP dependencies that aren't included here)
* `php artisan key:generate` (to set your app's encryption key)
* Edit the '.env' file with information about your local environment
* Start the server.  See the 'Getting Started' section of the Laravel docs (https://laravel.com/docs/8.x/installation) for full details, but there are three easy ways to get started:
    * `php artisan serve` on the command line, which starts a server at http://localhost:8000
    * Install this into an Apache/PHP server, with the 'web/public/' folder as the webroot
    * Use the included Dockerfile to run this in a Docker setup.

## Canvas Setup
First, you'll need to get a Developer Key in your Canvas installation, so that we can ask the user to log on via OAuth.
* In Canvas, go to the 'Admin > Developer Keys' menu, and create a new API key.
* Canvas will ask for a Redirect URI, which should be the '/oauth_redirect_complete' path on this server.  (e.g. "http://localhost:8000/oauth_redirect_complete" for a local developement instance).
* If you wish to use the 'Enforce Scopes' feature in Canvas, Peer Grading will need the following Scopes.  Check the 'Allow Include Parameters' checkbox as well.
    * Assignments:
        * url:GET|/api/v1/courses/:course_id/assignments
        * url:GET|/api/v1/courses/:course_id/assignments/:id
    * Courses:
        * url:GET|/api/v1/courses/:course_id/students
        * url:GET|/api/v1/users/:user_id/courses
    * Peer Reviews:
        * url:GET|/api/v1/courses/:course_id/assignments/:assignment_id/peer_reviews
    * Rubrics:
        * url:GET|/api/v1/courses/:course_id/rubrics/:id
    * Submissions:
        * url:GET|/api/v1/courses/:course_id/assignments/:assignment_id/submissions
        * url:GET|/api/v1/courses/:course_id/assignments/:assignment_id/submissions/:user_id
        * url:PUT|/api/v1/courses/:course_id/assignments/:assignment_id/submissions/:user_id
* The key is 'OFF' by default.  Turn it 'ON'.  Note its Access ID, which is the number under 'Details'.  Click 'Show Key' and note the key.  Enter these into your environment, as described above.

## Usage
When users access the home page, they'll be presented with a list of the classes they're a teacher or TA in, and which are currenly active.  They'll then be able to select an assignment in that class, and start grading.

## Meta
This is a product of Longhorn Open Ed Tech, a group building open-source education tools housed at the University of Texas at Austin. See our homepage for more info about us and to discuss collaboration possibilities and ideas for new development.

Distributed under the Gnu Affero license. See LICENSE for more information.

## Contributing
We welcome bug reports and feature suggestions via the 'Issues' tab in Github.

If you'd like to contribute a feature or other change, we welcome pull requests. For new features or other large changes, please open an issue first and tell us what you're proposing.
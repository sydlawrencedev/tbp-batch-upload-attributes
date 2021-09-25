
# Bulk attribute csv upload

Using The Bot Platform API to bulk upload attributes from a csv file
https://dev.thebotplatform.com

## Recommended settings

; Maximum allowed size for uploaded files.
; http://php.net/upload-max-filesize
upload_max_filesize = 42M

; Maximum size of POST data that PHP will accept.
; Its value may be 0 to disable the limit. It is ignored if POST data reading
; is disabled through enable_post_data_reading.
; http://php.net/post-max-size
post_max_size = 48M

; Maximum execution time of each script, in seconds
; http://php.net/max-execution-time
; Note: This directive is hardcoded to 0 for the CLI SAPI
max_execution_time = 600

You can visit /check.php to check set up is correct

## CLI tool

Along with this there is also a CLI tool which can be used as below:

```% php cli.php filename=test_10.csv client_id=CLIENT_ID

Open the following URL in a browser to continue
https://api.thebotplatform.com/oauth2/auth?state=state&response_type=code&approval_prompt=auto&redirect_uri=http%3A%2F%2F127.0.0.1%3A8080%2Fauthorization-code%2Fcallback&client_id=client_id
Getting an access token...
Loading attributes from test_10.csv
10 users to be updated
2 attributes to be updated
Unable to retrieve user with supplied identifier: donn@thebotplatform.org
Updated franz@thebotplatform.org
Updated lauren.gilman@thebotplatform.org
Updated paul@thebotplatform.org
Updated sophie@thebotplatform.org
Updated syd@thebotplatform.org
Updated tom@thebotplatform.org
Unable to retrieve user with supplied identifier: donn@thebotplatform.orga
Unable to retrieve user with supplied identifier: franz@thebotplatform.orga
Unable to retrieve user with supplied identifier: lauren.gilman@thebotplatform.orga
6 success / 4 failed / 10 total```
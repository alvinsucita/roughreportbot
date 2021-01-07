<?php

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
if (!file_exists('download')) {
    mkdir('download', 0777, true);
}
if (!file_exists('data.json')) {
    // $schedule = [];
    $job = [];
    // $items = ['schedule' => $schedule, 'jobs' => $job];
    $items = ['jobs' => $job];
    file_put_contents('data.json', json_encode($items));
}
if (!file_exists('file.json')) {
    $filename = [];
    $items = ['files' => $filename];
    file_put_contents('file.json', json_encode($items));
}
include 'madeline.php';
require_once 'vendor/autoload.php';

use danog\MadelineProto\EventHandler;
use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;

/**
 * Event handler class.
 */
class MyEventHandler extends EventHandler
{
    /**
     * @var int|string Username or ID of bot admin
     */
    const ADMIN = "dummybot123"; // Change this
    /**
     * Get peer(s) where to report errors
     *
     * @return int|string|array
     */
    public function getReportPeers()
    {
        return [self::ADMIN];
    }
    /**
      * Called on startup, can contain async calls for initialization of the bot
      *
      * @return void
      */
    public function onStart(){}
    /**
     * Handle updates from supergroups and channels
     *
     * @param array $update Update
     *
     * @return void
     */
    public function onUpdateNewChannelMessage(array $update): \Generator
    {
        return $this->onUpdateNewMessage($update);
    }
    /**
     * Handle updates from users.
     *
     * @param array $update Update
     *
     * @return \Generator
     */
    public function onUpdateNewMessage(array $update): \Generator
    {
        if ($update['message']['_'] === 'messageEmpty' || $update['message']['out'] ?? false) {
            return;
        }
        $res = \json_encode($update, JSON_PRETTY_PRINT);

        try {
            $message = $update["message"]["message"];
            // $parts = explode(' ', $message);
            $prefix = "";
            $isi = "";
            if(strpos($message, ' ') !== false){
                $prefix = substr($message, 0, strpos($message, ' '));
                $isi = substr($message, strpos($message, ' ')+1);
            }
            else{
                $prefix = $message;
            }

            // $schedule = json_decode(file_get_contents("data.json"), true)['schedule'];
            $jobs = json_decode(file_get_contents("data.json"), true)['jobs'];
            $files = json_decode(file_get_contents("file.json"), true)['files'];

            if($prefix == "/help"){
                yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>! = optional
REPORT BOT
naive

/broadcast <message> DONE!
Broadcast message ke semua user yang pernah message bot, supports file attachment (uploaded as document)
/allfiles DONE!
Get all files uploaded by/from every user to bot
Send any file to try (not working yet)
/getfile <filename> DONE!
get the uploaded file, uploaded as document (try /get test.png)
Send any media to upload files to bot! (not working for now, https://github.com/amphp/file/issues/36)

JOBS:
/newjob <task>##<dependancy>##<date start>##<approx finish date>##<difficulty>##<note> DONE!
Insert a job, default status is Not Started (add --<status> for override)
Don't skip any parameters, if theres no date set just type -
Dates are dd/mm/yyyy
/joblist <!keyword> DONE!
Get all jobs, 4 status type (done, pending, drop, ongoing)
<keyword> for finding job name from description.
Return:
<job_id>: detail
/start <job_id> DONE!
Set job status from done to ongoing
/done <job_id> DONE!
Set job status to Finished
/pending <job_id> DONE!
Set job status to On Hold
/drop <job_id> DONE!
Set job status to Cancelled
/ongoing <job_id> DONE!
Set job status to In Progress
/getschedule <!keyword>
Get schedule, keyword untuk cari job
Result:
<job_id>: detail
/setschedule <job_id>##<start date>##<approx finish date>##<!finish date> DONE!
Overwrite schedule

WIP</code>", 'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 'parse_mode' => 'HTML']);
            }
            // upload
            else if($prefix == "/getfile"){
                if($isi == ""){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No file name found! Try /allfiles</code>", 'parse_mode' => 'HTML']);
                }
                $MessageMedia = yield $this->messages->uploadMedia([
                    'media' => [
                        '_' => 'inputMediaUploadedDocument',
                        'file' => 'download/'.$isi
                    ],
                ]);
                yield $this->messages->sendMedia([
                    'peer' => $update,
                    'media' => $MessageMedia,
                    'message' => $isi
                ]);
            }
            else if($prefix == "/allfiles"){
                if(empty($files)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No files uploaded yet!</code>", 'parse_mode' => 'HTML']);
                }
                else{
                    $textresult = "";
                    for($x = 0; $x < count($files); $x++){
                        $textresult = $textresult.$files[$x]['filename'].'
';
                    }
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>".$textresult."</code>", 'parse_mode' => 'HTML']);
                }
            }
            else if($prefix == "/setschedule"){
                $parts = explode('##', $isi);
                if(empty($jobs)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No task has been set yet!</code>", 'parse_mode' => 'HTML']);
                }
                else if($isi == "" || count($parts) < 3){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Please put the correct parameters and try again!</code>", 'parse_mode' => 'HTML']);
                }
                else {
                    if(!isset($jobs[$parts[0]]))
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job not found! Type /joblist for all jobs.</code>", 'parse_mode' => 'HTML']);
                    else{
                        $jobs[$parts[0]]['start'] = $parts[1];
                        $jobs[$parts[0]]['approx'] = $parts[2];
                        if(isset($parts[3]))
                            $jobs[$parts[0]]['complete'] = $parts[3];
                        $items = ['jobs' => $jobs];
                        if (file_put_contents('data.json', json_encode($items))){
                            $jobs = json_decode(file_get_contents("data.json"), true)['jobs'];
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Done!</code>", 'parse_mode' => 'HTML']);
                        }
                        else
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Error!</code>", 'parse_mode' => 'HTML']);
                    }
                }
            }
            else if($prefix == "/getschedule"){
                $ress = $jobs;
                if(empty($jobs)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No schedule has been set yet!</code>", 'parse_mode' => 'HTML']);
                }
                else{
                    if($isi != ""){
                        $ress = array_filter($jobs, function($jobs) use ($isi) {
                            return ( strpos($jobs['job'], $isi) !== false );
                        });
                    }
                    if(empty($ress)){
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Task not found!</code>", 'parse_mode' => 'HTML']);
                    }
                    $textresult = "";
                    for($x = 0; $x < count($ress); $x++){
                        $textresult = $textresult.$x.': '.$ress[$x]['job'].'
Start: '.$ress[$x]['start'].'
Approx. Finish: '.$ress[$x]['approx'].'
Actual Finish: '.$ress[$x]['complete'].'
';
                    }
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>".$textresult."</code>", 'parse_mode' => 'HTML']);
                }
            }
            else if($prefix == "/newjob"){
                $parts = explode('##', $isi);
                if(count($parts) < 6 || $isi = "")
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Please put a job title and try again!</code>", 'parse_mode' => 'HTML']);
                else {
                    $info = yield $this->getInfo($update['message']['from_id']);
                    $user = "";
                    if(isset($info['User']['first_name'])){
                        $user = $info['User']['first_name'];
                    }
                    else{
                        $user = $info['User']['phone'];
                    }
                    $customstatus = explode('--', $parts[5]);
                    if(isset($customstatus[1])) $status = $customstatus[1];
                    else $status = "Not Started";
                    $array = Array (
                        "0" => Array (
                            "job" => $parts[0],
                            "dependancy" => $parts[1],
                            "status" => $status,
                            "start" => $parts[2],
                            "approx" => $parts[3],
                            "complete" => "-",
                            "assigned-to" => "-",
                            "owner" => $user,
                            "difficulty" => $parts[4],
                            "description" => $customstatus[0]
                        )
                    );
                    $merge = array_merge($jobs, $array);
                    $items = ['jobs' => $merge];
                    if (file_put_contents('data.json', json_encode($items)))
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Done!</code>", 'parse_mode' => 'HTML']);
                    else
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Error!</code>", 'parse_mode' => 'HTML']);
                }
            }
            else if($prefix == "/joblist"){
                $ress = $jobs;
                if(empty($ress)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No task has been set yet!</code>", 'parse_mode' => 'HTML']);
                }
                else{
                    if($isi != ""){
                        $ress = array_filter($jobs, function($jobs) use ($isi) {
                            return ( strpos($jobs['job'], $isi) !== false );
                        });
                    }
                    if(empty($ress)){
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Task not found!</code>", 'parse_mode' => 'HTML']);
                    }
                    else{
                        $textresult = "";
                        for($x = 0; $x < count($ress); $x++){
                            $textresult = $textresult.$x.': '.$ress[$x]['job'].' ('.$ress[$x]['status'].')
Dependancy: '.$ress[$x]['dependancy'].'
Assigned to: '.$ress[$x]['assigned-to'].'
Owner: '.$ress[$x]['owner'].'
Difficulty: '.$ress[$x]['difficulty'].'
Note: '.$ress[$x]['description'].'
';
                        }
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>".$textresult."</code>", 'parse_mode' => 'HTML']);
                    }
                }
            }
            else if($prefix == "/done"){
                if(empty($jobs)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No task has been set yet!</code>", 'parse_mode' => 'HTML']);
                }
                else if($isi == ""){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Blank parameter! Type /help</code>", 'parse_mode' => 'HTML']);
                }
                else{
                    if(!isset($jobs[$isi]))
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job not found! Type /joblist for all jobs.</code>", 'parse_mode' => 'HTML']);
                    else{
                        $jobs[$isi]['status'] = "Finished";
                        $items = ['jobs' => $jobs];
                        if (file_put_contents('data.json', json_encode($items))){
                            $jobs = json_decode(file_get_contents("data.json"), true)['jobs'];
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job ID ".$isi." is done!</code>", 'parse_mode' => 'HTML']);
                        }
                        else
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Error!</code>", 'parse_mode' => 'HTML']);
                    }
                }
            }
            else if($prefix == "/pending"){
                if(empty($jobs)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No task has been set yet!</code>", 'parse_mode' => 'HTML']);
                }
                else if($isi == ""){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Blank parameter! Type /help</code>", 'parse_mode' => 'HTML']);
                }
                else{
                    if(!isset($jobs[$isi]))
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job not found! Type /joblist for all jobs.</code>", 'parse_mode' => 'HTML']);
                    else{
                        $jobs[$isi]['status'] = "On Hold";
                        $items = ['jobs' => $jobs];
                        if (file_put_contents('data.json', json_encode($items))){
                            $jobs = json_decode(file_get_contents("data.json"), true)['jobs'];
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job ID ".$isi." is set to On Hold!</code>", 'parse_mode' => 'HTML']);
                        }
                        else
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Error!</code>", 'parse_mode' => 'HTML']);
                    }
                }
            }
            //batal
            else if($prefix == "/drop"){
                if(empty($jobs)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No task has been set yet!</code>", 'parse_mode' => 'HTML']);
                }
                else if($isi == ""){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Blank parameter! Type /help</code>", 'parse_mode' => 'HTML']);
                }
                else{
                    if(!isset($jobs[$isi]))
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job not found! Type /joblist for all jobs.</code>", 'parse_mode' => 'HTML']);
                    else{
                        $jobs[$isi]['status'] = "Cancelled";
                        $items = ['jobs' => $jobs];
                        if (file_put_contents('data.json', json_encode($items))){
                            $jobs = json_decode(file_get_contents("data.json"), true)['jobs'];
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job ID ".$isi." is dropped!</code>", 'parse_mode' => 'HTML']);
                        }
                        else
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Error!</code>", 'parse_mode' => 'HTML']);
                    }
                }
            }
            //batal selesai
            else if($prefix == "/ongoing"){
                if(empty($jobs)){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>No task has been set yet!</code>", 'parse_mode' => 'HTML']);
                }
                else if($isi == ""){
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Blank parameter! Type /help</code>", 'parse_mode' => 'HTML']);
                }
                else{
                    if(!isset($jobs[$isi]))
                        yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job not found! Type /joblist for all jobs.</code>", 'parse_mode' => 'HTML']);
                    else{
                        $jobs[$isi]['status'] = "In Progress";
                        $items = ['jobs' => $jobs];
                        if (file_put_contents('data.json', json_encode($items))){
                            $jobs = json_decode(file_get_contents("data.json"), true)['jobs'];
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Job ID ".$isi." is set back to ongoing!</code>", 'parse_mode' => 'HTML']);
                        }
                        else
                            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Error!</code>", 'parse_mode' => 'HTML']);
                    }
                }
            }
            else if($prefix == "/broadcast"){
                $info = yield $this->getInfo($update['message']['from_id']);
                $broaduser = "";
                if(isset($info['User']['username'])){
                    $broaduser = $info['User']['username'];
                }
                else{
                    $broaduser = $info['User']['phone'];
                }
                $dialogs = yield $this->getDialogs();
                yield $this->messages->sendMessage(['peer' => $update, 'message' => 'Broadcast succesfully sent!', 'parse_mode' => 'HTML']);
                foreach ($dialogs as $peer) {
                    if(isset($peer['user_id']) && $peer['user_id'] == $info['User']['id']){
                        continue;
                    }
                    if(isset($peer) && $peer == $info['Peer']){
                        continue;
                    }
                    if (isset($update['message']['media']) && $update['message']['media']['_'] !== 'messageMediaGame'){
                        yield $this->messages->sendMedia([
                            'peer' => $peer,
                            'media' => $update,
                            'message' => 'Broadcast from @'.$broaduser.
':
<code>'.$isi.'</code>', 'parse_mode' => 'HTML']);
                    }
                    else{
                        yield $this->messages->sendMessage(['peer' => $peer, 'message' => 'Broadcast from @'.$info['User']['username'].
':
<code>'.$isi.'</code>', 'parse_mode' => 'HTML']);
                    }
                }
            }
            //ini untuk download?
            else if (isset($update['message']['media']) && $update['message']['media']['_'] !== 'messageMediaGame'){
                $info = yield $this->getDownloadInfo($update);
                // yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>".$info['name'].'.'.$info['ext']."</code>", 'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 'parse_mode' => 'HTML']);
                // yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>".$res."</code>", 'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 'parse_mode' => 'HTML']);
                // yield $this->downloadToFile($info, 'download/'.$info['name'].'.'.$info['ext']);
                // $output_file_name = yield $this->downloadToDir($update['message']['media'], 'download/');
                $array = Array (
                    "0" => Array (
                        "filename" => $info['name'].'.'.$info['ext'],
                    )
                );
                $merge = array_merge($files, $array);
                $items = ['files' => $merge];
                if (file_put_contents('file.json', json_encode($items))){
                    $files = json_decode(file_get_contents("file.json"), true)['files'];
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Done!</code>", 'parse_mode' => 'HTML']);
                }
                else
                    yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Error!</code>", 'parse_mode' => 'HTML']);
            }
            else if(substr($prefix, 0, 1) == "/"){
                yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>Wrong / unrecognized command! Type /help to get all commands.</code>", 'parse_mode' => 'HTML']);
            }
            // else{
            //     yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>".$update["message"]["message"]."</code>", 'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 'parse_mode' => 'HTML']);
            // }
        } catch (RPCErrorException $e) {
            $this->report("Surfaced: $e");
        } catch (Exception $e) {
            if (\stripos($e->getMessage(), 'invalid constructor given') === false) {
                $this->report("Surfaced: $e");
            }
        }
    }
    // onupdate over
}
$settings = [
    'logger' => [
        'logger_level' => 5
    ],
    'serialization' => [
        'serialization_interval' => 30,
    ],
];

$MadelineProto = new API('bot.madeline', $settings);
$MadelineProto->startAndLoop(MyEventHandler::class);

<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;


class UserTest extends Tests\BrowserKitTestCase
{

    /******************* Why is it done this way ? ***************/

    /*
    I want my tests runs on a clean DB, and each test in the right order, like I would do by hand.
    The first tests migrates the testing DB
    Sounds simplier like this for me, I don't want the database being remigrated after each test.
    Only after the whole suite has been run.

    You need a agorakit_testing DB available for those tests to run.


    Our scenario :

    - we have Roberto, our admin
    - we also have Newbie, another user

    - Roberto creates 2 groups, an open one and a closed one
    - Newbie tries to join both

    What happens ?


    */

    public function testSetupItAll()
    {
        Artisan::call('migrate:refresh');

        $this->visit('/')
        ->see('Agorakit');
    }

    /**
    * A basic test example.
    *
    * @return void
    */
    public function testUserRegistration()
    {
        Mail::fake();

        $this->visit('/register')
        ->type('Roberto', 'name')
        ->type('admin@agorakit.org', 'email')
        ->type('123456', 'password')
        ->type('123456', 'password_confirmation')
        ->press('Register')
        ->see('Agorakit');

        $this->seeInDatabase('users', ['email' => 'admin@agorakit.org']);
    }

    public function testGroupCreation()
    {
        $user = App\User::where('email', 'admin@agorakit.org')->first();

        $user->confirmEmail();

        $this->actingAs($user)
        ->visit('groups/create')
        ->see('Create a new group')
        ->type('Test group', 'name')
        ->type('this is a test group', 'body')
        ->press('Create the group')
        ->see('Test group');
    }

    public function testDiscussionCreation()
    {
        $user = App\User::where('email', 'admin@agorakit.org')->first();

        $group = App\Group::where('name', 'Test group')->first();

        $this->actingAs($user)
        ->visit('/groups/'.$group->id.'/discussions/create')
        ->see('Create')
        ->type('Test discussion', 'name')
        ->type('this is a test discussion', 'body')
        ->press('Create')
        ->see('Test discussion');
    }

    public function testActionCreation()
    {
        $user = App\User::where('email', 'admin@agorakit.org')->first();

        $group = App\Group::where('name', 'Test group')->first();

        $this->actingAs($user)
        ->visit('/groups/'.$group->id.'/actions/create')
        ->see('Add an event')
        ->type('Test action', 'name')
        ->type('this is a test action in the agenda', 'body')
        ->type('Bruxelles', 'location')
        ->type('2016-01-01 11:00', 'start')
        ->type('2016-01-01 15:00', 'stop')
        ->press('Create')
        ->seeInDatabase('actions', ['name' => 'Test action'])
        ->see(trans('action.create_one_button'));
    }

    public function testPrivateGroupCreation()
    {
        $user = App\User::where('email', 'admin@agorakit.org')->first();

        $this->actingAs($user)
        ->visit('groups/create')
        ->see('Create a new group')
        ->type('Private test group', 'name')
        ->type('this is a test group', 'body')
        ->select('1', 'group_type')
        ->press('Create the group')
        ->see('Private test group')
        ->see('Closed group');
    }

    public function testASecondUserIsRegistering()
    {
        Mail::fake();

        $this->visit('/register')
        ->type('Newbie', 'name')
        ->type('newbie@agorakit.org', 'email')
        ->type('123456', 'password')
        ->type('123456', 'password_confirmation')
        ->press('Register')
        ->see('Agorakit');

        $this->seeInDatabase('users', ['email' => 'newbie@agorakit.org']);
    }

    public function testNewbieCanJoinOpenGroup()
    {
        $group = App\Group::where('name', 'Test group')->first();

        $user = App\User::where('email', 'newbie@agorakit.org')->first();

        $user->confirmEmail();

        $this->actingAs($user)
        ->visit('/groups/'.$group->id.'/join')
        ->see('Join the group')
        ->press('Join')
        ->see('Welcome');
    }

    public function testNewbieCantJoinPrivateGroup()
    {
        $group = App\Group::where('name', 'Private test group')->first();

        $user = App\User::where('email', 'newbie@agorakit.org')->first();


        $this->actingAs($user)
        ->visit('/groups/'.$group->id.'/join')
        ->see(trans('membership.apply_for_group'));
    }

    public function testNewbieCanApplyToPrivateGroup()
    {
        $group = App\Group::where('name', 'Private test group')->first();

        $user = App\User::where('email', 'newbie@agorakit.org')->first();


        $this->actingAs($user)
        ->visit('/groups/'.$group->id.'/join')
        ->press(trans('membership.apply'))
        ->see(trans('membership.application_stored'));
    }


    public function testAdminCanConfirmCandidateToPrivateGroup()
    {
        // don't you like this function name?
        $group = App\Group::where('name', 'Private test group')->first();

        $user = App\User::where('email', 'admin@agorakit.org')->first();
        $newbie = App\User::where('email', 'newbie@agorakit.org')->first();

        $this->actingAs($user)
        ->visit('groups/'.$group->id.'/users')
        ->click(trans('messages.confirm_user'))
        ->see(trans('messages.user_made_member_successfuly'));

        $this->seeInDatabase('membership', ['user_id' => $newbie->id, 'membership' => '20']);

    }



    public function testNewbieCanCreateGroup()
    {
        $user = App\User::where('email', 'newbie@agorakit.org')->first();

        $this->actingAs($user)
        ->visit('groups/create')
        ->see('Create a new group')
        ->type('Test group of newbie', 'name')
        ->type('this is a test group', 'body')
        ->press('Create the group')
        ->see('Test group of newbie');
    }

    public function testRobertoIsAdminOfTestGroup()
    {
        $user = App\User::where('email', 'admin@agorakit.org')->first();
        $group = App\Group::where('name', 'Test group')->first();
        $this->assertTrue($user->isAdminOf($group));
    }

    public function testNewbieIsNotAdminOfTestGroup()
    {
        $user = App\User::where('email', 'newbie@agorakit.org')->first();
        $group = App\Group::where('name', 'Test group')->first();
        $this->assertFalse($user->isAdminOf($group));
    }

    public function testNewbieIsAdminOfTestGroupOfNewbie()
    {
        $user = App\User::where('email', 'newbie@agorakit.org')->first();
        $group = App\Group::where('name', 'Test group of newbie')->first();
        $this->assertTrue($user->isAdminOf($group));
    }


    /* now let's test emails */

    public function testNotificationReceived()
    {

        $group = App\Group::where('name', 'Test group')->firstOrFail();
        $user = App\User::where('email', 'newbie@agorakit.org')->firstOrFail();
        $roberto = App\User::where('email', 'admin@agorakit.org')->firstOrFail();

        // let's first create a discussion in test group that newbie has not read yet, and a long time ago
        $discussion = new \App\Discussion;
        $discussion->name = 'Notify me of this interesting discussion';
        $discussion->body ='Such an interesting discussion blablbla';
        $discussion->user_id = $roberto->id;
        $discussion->group_id = $group->id;
        $discussion->created_at = '2001-01-01';
        $discussion->total_comments = 1;

        $group->discussions()->save($discussion);


        // fake newbie's membership in order to be in the situation of newbie must be notified
        $membership = App\Membership::where('user_id', $user->id)->where('group_id', $group->id)->firstOrFail();
        $membership->notified_at = '2001-01-01';
        $membership->save();


        // fake our mail sending
        Mail::fake();

        // send notifications if any
        Artisan::call('notifications:send');

        // Assert a message was sent to the given users...
        Mail::assertSent(\App\Mail\Notification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

}

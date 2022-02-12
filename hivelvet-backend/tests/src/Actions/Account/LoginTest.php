<?php

declare(strict_types=1);

/*
 * Hivelvet open source platform - https://riadvice.tn/
 *
 * Copyright (c) 2022 RIADVICE SUARL and by respective authors (see below).
 *
 * This program is free software; you can redistribute it and/or modify it under the
 * terms of the GNU Lesser General Public License as published by the Free Software
 * Foundation; either version 3.0 of the License, or (at your option) any later
 * version.
 *
 * Hivelvet is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License along
 * with Hivelvet; if not, see <http://www.gnu.org/licenses/>.
 */

namespace Actions\Account;

use Base;
use Enum\UserRole;
use Enum\UserStatus;
use Fake\UserFaker;
use Faker\Factory as Faker;
use Models\User;
use ReflectionException;
use Test\Scenario;

/**
 * @internal
 * @coversNothing
 */
final class LoginTest extends Scenario
{
    protected $group = 'Action User Login';

    /**
     * @param Base $f3
     *
     * @return array
     */
    public function testAuthenticateInvalidUser($f3)
    {
        $test  = $this->newTest();
        $faker = Faker::create();

        $data = ['email' => $faker->email, 'password' => $faker->password(8)];
        $f3->mock('POST /account/login', $this->postData($data));
        $test->expect('Invalid password' === $f3->get('form_errors.email'), 'Login with non existing credentials shows error');

        $data = ['email' => $email = $faker->firstName, 'password' => $faker->password(8)];
        $f3->mock('POST /account/login', $this->postData($data));
        $test->expect($f3->get('form_errors.email') === "- \"{$email}\" must be valid email", 'Login with invalid email format show an error');

        $dataUsedCsrf = ['email' => $faker->email, 'password' => $faker->password(8), 'csrf_token' => $faker->md5];
        $f3->mock('POST /account/login', $dataUsedCsrf);
        $test->expect('CSRF token used or not set' === $f3->get('SESSION.form_errors.csrf_token'), 'Login with used CSRF Token refused');

        $dataHackedCsrf = ['email' => $faker->email, 'password' => $faker->password(8), 'csrf_token' => $faker->md5];
        $this->postData($dataHackedCsrf);
        $dataHackedCsrf['csrf_token'] = $faker->md5;
        $f3->mock('POST /account/login', $dataHackedCsrf);
        $test->expect('Invalid CSRF token' === $f3->get('SESSION.form_errors.csrf_token'), 'Login with invalid CSRF Token refused');

        return $test->results();
    }

    /**
     * @param $f3
     *
     * @throws ReflectionException
     *
     * @return array
     */
    public function testAuthenticateExistingUser($f3)
    {
        $test = $this->newTest();
        $user = UserFaker::create(UserRole::ADMIN);

        $data = ['email' => $user->email, 'password' => UserRole::ADMIN . UserRole::ADMIN];
        $f3->mock('POST /account/login', $this->postData($data));

        // $test->expect($this->reroutedTo('dashboard'), 'Login with correct credentials rerouted to dashboard');
        $test->expect($f3->exists('SESSION.user'), 'Sessions is aware that the user us logged in');

        UserFaker::logout();

        $data = ['email' => $user->email, 'password' => UserRole::ADMIN . UserRole::ADMIN];
        $f3->mock('POST /account/login', $this->postData($data));

        $f3->mock('GET /account/login');
        // $test->expect($this->reroutedTo('dashboard'), 'Already logged in user is rerouted to dashboard');

        return $test->results();
    }

    /**
     * @param Base $f3
     *
     * @return array
     */
    public function testAuthenticateExistingInactiveUser($f3)
    {
        $test           = $this->newTest();
        $faker          = Faker::create();
        $raw_password   = $faker->password(8);
        $status         = UserStatus::INACTIVE;
        $user           = new User();
        $user->email    = $faker->email;
        $user->username = $faker->userName;
        $user->password = $raw_password;
        $user->status   = $status;
        $user->save();

        $f3->mock('GET /account/login');

        $data = ['email' => $user->email, 'password' => $raw_password];
        $f3->mock('POST /account/login', $this->postData($data));

        // $test->expect($this->reroutedTo('login'), 'Login with correct credentials and ' . $status . ' account rerouted lo login');

        return $test->results();
    }
}

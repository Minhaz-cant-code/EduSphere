# EduSphere – PHPUnit Testing

## What is being tested

The tests cover the shared logic in `includes/functions.php` (the code every page uses).

| File | What it checks | Requirement |
|---|---|---|
| `tests/Unit/GradeTest.php` | Grade & GPA for every boundary (80, 79.5, 70 … 33, 32), totals other than 100, total = 0 | FR-EXM-3 |
| `tests/Unit/FeeStatusTest.php` | Unpaid / Partial / Paid / overpaid | FR-FEE-2 |
| `tests/Unit/HelpersTest.php` | `e()` escapes HTML (XSS), initials, role labels, ৳ money format, dates, `url()`, badges, `post()` / `get_int()` | NFR-2 |
| `tests/Unit/SessionAndCsrfTest.php` | Logged-in helpers, flash messages, CSRF token format and check | FR-AUTH, NFR security |
| `tests/Database/AttendanceTest.php` | Attendance counts and %, "Late counts as attended", date range, one mark per day | FR-ATT-2 |
| `tests/Database/FeeTest.php` | Total / paid / due from `fees − payments`, a new payment lowers the due | FR-FEE-2 |
| `tests/Database/UserTest.php` | Passwords are bcrypt-hashed, unique usernames, `user_exists()`, demo logins work, login by email | FR-AUTH-1 |
| `tests/Database/NoticeTest.php` | Each role sees the right notices, expired ones are hidden, newest first | FR-NOT-1/2 |
| `tests/Database/AccessTest.php` | A teacher can manage only his own batches/subjects; admin can manage everything | FR-AUTH-4 |
| `tests/Database/ResultsTest.php` | Every stored grade matches `calc_grade()` | FR-EXM-3 |

**Unit tests** check one function at a time with fixed inputs.
**Database tests** run the real SQL queries against a separate database.

## The test database

Tests **never** touch your real `edusphere` database.

- `phpunit.xml` sets `DB_NAME=edusphere_test`, and `config/config.php` reads it
  (`getenv('DB_NAME') ?: 'edusphere'`). When the site runs in the browser nothing changes.
- `tests/bootstrap.php` deletes and re-creates `edusphere_test` from `database/edusphere.sql`
  at the start of every run, so the demo data is always the same.
- Each database test runs inside a transaction that is rolled back at the end
  (`tests/Database/DatabaseTestCase.php`), so one test can't affect another.

## How to run

1. Start **MySQL** in the XAMPP Control Panel.
2. Double-click **`run_tests.bat`**. The first time it downloads `phpunit.phar`
   (PHPUnit packed into one file). Results are also saved to `tests-output.txt`.

From a Command Prompt in `C:\xampp\htdocs\edusphere`:

```bat
php phpunit.phar --testdox                 :: all tests, readable names
php phpunit.phar                           :: short output (dots)
php phpunit.phar --testsuite Unit          :: only the unit tests (fast)
php phpunit.phar --testsuite Database      :: only the database tests
php phpunit.phar --filter GradeTest        :: one test class
```

(If `php` is not found, use `C:\xampp\php\php.exe` instead.)

Each `.` (or ✔ with `--testdox`) is a passed test. `F` means a failure: PHPUnit prints
the expected and actual values and the line number.

*Composer alternative:* `composer install` then `vendor\bin\phpunit` also works, but XAMPP needs
`extension=zip` enabled in `C:\xampp\php\php.ini` first.

## Writing a new test

1. Make a file in `tests/Unit/` (or `tests/Database/` if it needs the database), ending in `Test.php`.
2. Make a class that extends `TestCase` (or `DatabaseTestCase`), with methods whose names start with `test`.
3. In each method: **arrange** the input, **act** (call the function), **assert** the result.

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase
{
    public function testSeventyFivePercentIsGradeA(): void
    {
        $result = calc_grade(75, 100);          // act
        $this->assertSame(['A', 4.00], $result); // assert
    }
}
```

Common assertions: `assertSame` (equal and same type), `assertEquals` (equal value),
`assertTrue` / `assertFalse`, `assertCount`, `assertContains`, `assertStringContainsString`,
`expectException`.

## What is not covered

Code written directly inside page files (for example the lockout counter in `login.php`) can't be
called from a test. To test it, move that logic into a function in `includes/functions.php`
and write a test for the function.

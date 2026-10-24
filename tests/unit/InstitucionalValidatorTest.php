<?php

use App\Libraries\InstitucionalValidator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class InstitucionalValidatorTest extends CIUnitTestCase
{
    public function testSoloAceptaDominioInstitucional(): void
    {
        putenv('ALLOWED_EMAIL_DOMAINS=unac.edu.pe');
        $rule = new InstitucionalValidator();

        $this->assertTrue($rule->institutional_email('ana@unac.edu.pe'));
        $this->assertTrue($rule->institutional_email('Ana@UNAC.EDU.PE'));
        $this->assertFalse($rule->institutional_email('ana@gmail.com'));
        $this->assertFalse($rule->institutional_email('ana@unac.edu.pe.evil.com'));
        $this->assertFalse($rule->institutional_email('ana@evilunac.edu.pe'));
        $this->assertFalse($rule->institutional_email(null));
    }
}

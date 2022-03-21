<?php

namespace Drupal\piv_contest_judging_session\Service;

use Drupal\piv_contest_judging_session\Entity\JudgingSession;
use Drupal\user\Entity\User;

class JudgeSession {

  public function totalNumberOfRecitations(JudgingSession $session, User $judge): int {
    return 3;
  }

  public function numberOfRecitationsEvaluatedByJudge(JudgingSession $session, User $judge): int {
    return 2;
  }

  public function isSessionEvaluatedByJudge(JudgingSession $session, User $judge): bool {
    return FALSE;
  }

}

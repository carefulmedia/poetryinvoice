About this module (specially the ScoreController).

The module was created in a way where the rounds were synchronized for
english and french judges, displaying a round/total progress where the
total was the sum of all recitations (all languages). Later it was
modified so the total was only the number of recitations the judge can
judge (filtering by language).

Given the above said, there are some workarounds to make it work without
having to change too much of the original code. Pay attention specially
to the $is_locked, $is_last_recitation and the $round count, these
variables have special cases depending if the judge can judge or not the
current recitation.

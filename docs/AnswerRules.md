<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Answer rules

Form owners can restrict what respondents enter in text answers. This page lists the rules and explains what happens when you change them. For the stored settings see [DataStructure](./DataStructure.md#extra-settings).

## Where to set them

Switch on "Answer rules" in the question menu (the "…" button next to the title). A row of rules then shows below the answer field, like Google's "Response validation" and Microsoft's "Restrictions". "Add rule" adds a rule, the "×" at the end of a rule removes it, and switching "Answer rules" off removes all rules of the question.

Each rule has a kind, a condition where the kind has one, and its values. Each kind can be used once per question, so a question has at most one length, one number rule and so on. Which kinds you can add depends on the question:

| Question                                  | Kinds of rules                                                         |
| ----------------------------------------- | ---------------------------------------------------------------------- |
| Long answer                               | Length: at least, at most or between a number of characters            |
| Short answer, input type Text             | Length, "Must contain" a text, "Must not contain" a text               |
| Short answer, input type Number           | Number: a number rule (see below); "Whole numbers only"                |
| Short answer, input type URL              | None; only `http` and `https` addresses are accepted                   |
| Short answer, email address, phone number | None; a valid address or number is required                            |
| Short answer, custom regular expression   | None; the answer must match the expression, set in the input type menu |

The input type itself is still chosen in the input type menu (the icon at the end of the answer field).

The number rules are: between, not between, greater than, at least, less than, at most, equal to and not equal to. Numbers may have decimals unless "Whole numbers only" is set. "Must contain" and "Must not contain" ignore upper and lower case.

## What respondents see

- A hint above the answer field names every rule, for example "Enter between 3 and 12 characters" or "Enter a number greater than 0 · Enter a whole number".
- Text fields with a length limit show a character counter below the field, like "11 / 40". Typing stops at the maximum length.
- An answer breaking a rule shows the first broken rule as an error and cannot be submitted.
- The server checks every rule again when a response is submitted or edited, so the rules also hold for answers sent through the API.

## How changing rules behaves

### Changing the input type

Switching a short answer to another input type removes all rules of the previous one: lengths, the number rule, "Whole numbers only" and the required texts. Add them again for the new input type.

### Changing the number rule

A new number rule starts as between 0 and 100. When you pick another condition, the editor keeps your number where it can, so you only adjust what is new:

| New rule                                                  | Number it starts with                                   |
| --------------------------------------------------------- | ------------------------------------------------------- |
| At least, less than, greater than, equal to, not equal to | Your lowest number, or your highest if you only set one |
| At most                                                   | Your highest number, or your lowest if you only set one |

Switching to **between** or **not between** proposes a range from your number:

| Current rule                   | Proposed range                                       |
| ------------------------------ | ---------------------------------------------------- |
| A range, like between 1 and 10 | Stays 1 to 10                                        |
| At most 50, greater than 50    | 0 to 50                                              |
| At least 18, less than 18      | 18 to 100, up to the next number with one more digit |
| Equal to 7, not equal to 7     | 0 to 14, zero to double the number                   |

Zero and negative numbers are mirrored, so the lowest number always stays below the highest: at most −5 proposes −10 to −5, equal to −7 proposes −14 to 0, and equal to 0 proposes 0 to 10.

"Whole numbers only" stays on when you change the number rule.

### Rules that mean the same

If you set the same lowest and highest number under "Between", the editor shows the rule as "Equal to" the next time you open the form, and under "Not between" as "Not equal to". Both mean the same, so respondents see no difference.

### Rules that do not fit together

The editor does not save a value that would make a rule impossible to meet, and shows the reason below the field when you leave it; the previous value stays. The server rejects the same settings.

- A minimum above its maximum: "The minimum must not be greater than the maximum". This applies to lengths and to the lowest and highest number of a range.
- A length below 1 or above 4096 characters, the longest answer Forms stores.
- A required text that contains the excluded text, like "Must contain" "GmbH" with "Must not contain" "mb": "The required text contains the excluded text".
- A required text longer than the maximum length, like "Must contain" "GmbH" with at most 3 characters: "The required text is longer than the maximum length".
- A range without a whole number when "Whole numbers only" is set, like between 1.2 and 1.8 or equal to 2.5: "No whole number is in this range".

Clearing the text of "Must contain" or "Must not contain" removes that rule.

### Existing responses

Changing a rule does not change or remove responses already submitted. The new rules apply to the next submission, and to a response when it is edited.

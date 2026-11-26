<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Answer rules

Form owners can restrict what respondents enter in text answers. This page lists the rules and explains what happens when you change them. For the stored settings see [DataStructure](./DataStructure.md#extra-settings).

## Where to set them

- **Long answer**: in the question menu, "Require a minimum length" and "Limit the maximum length".
- **Short answer**: in the input type menu (the icon at the end of the answer field). Which rules you can set depends on the input type:

| Input type                  | Rules                                                                     |
| --------------------------- | ------------------------------------------------------------------------- |
| Text                        | Minimum and maximum length, "Must contain", "Must not contain"            |
| Number                      | "Number rule" submenu: a number rule (see below) and "Whole numbers only" |
| URL                         | Only `http` and `https` addresses                                         |
| Email address, phone number | A valid address or number                                                 |
| Custom regular expression   | The answer must match the expression                                      |

The number rules are: any number, between, not between, greater than, at least, less than, at most, equal to and not equal to. Numbers may have decimals unless "Whole numbers only" is on. "Must contain" and "Must not contain" ignore upper and lower case.

## What respondents see

- A hint above the answer field names every rule, for example "Enter between 3 and 12 characters" or "Enter a number greater than 0 · Enter a whole number".
- Text fields with a length limit show a character counter below the field, like "11 / 40". Typing stops at the maximum length.
- An answer breaking a rule shows the first broken rule as an error and cannot be submitted.
- The server checks every rule again when a response is submitted or edited, so the rules also hold for answers sent through the API.

## How changing rules behaves

### Changing the input type

Switching a short answer to another input type removes all rules of the previous one: lengths, the number rule, "Whole numbers only" and the required texts. Set them again for the new input type.

### Changing the number rule

When you pick another number rule, the editor keeps your number where it can, so you only adjust what is new:

| New rule                                                  | Number it starts with                                   |
| --------------------------------------------------------- | ------------------------------------------------------- |
| At least, less than, greater than, equal to, not equal to | Your lowest number, or your highest if you only set one |
| At most                                                   | Your highest number, or your lowest if you only set one |
| Any number                                                | None, the numbers are removed                           |

Switching to **between** or **not between** proposes a range from your number:

| Current rule                   | Proposed range                                       |
| ------------------------------ | ---------------------------------------------------- |
| A range, like between 1 and 10 | Stays 1 to 10                                        |
| At most 50, greater than 50    | 0 to 50                                              |
| At least 18, less than 18      | 18 to 100, up to the next number with one more digit |
| Equal to 7, not equal to 7     | 0 to 14, zero to double the number                   |
| Any number                     | 0 to 100                                             |

Zero and negative numbers are mirrored, so the lowest number always stays below the highest: at most −5 proposes −10 to −5, equal to −7 proposes −14 to 0, and equal to 0 proposes 0 to 10.

"Whole numbers only" stays on when you change the number rule.

### Rules that mean the same

If you set the same lowest and highest number under "Between", the editor shows the rule as "Equal to", and under "Not between" as "Not equal to". Both mean the same, so respondents see no difference.

### Limits that do not fit together

- A minimum above its maximum is not saved: the editor shows "The minimum must not be greater than the maximum" below the field and keeps the previous value. This applies to lengths and to the lowest and highest number of a range.
- A length can be at most 4096 characters, the longest answer Forms stores. "Limit the maximum length" starts at 4096; "Require a minimum length" starts at 1.
- Clearing "Must contain" or "Must not contain" removes that rule.

### Existing responses

Changing a rule does not change or remove responses already submitted. The new rules apply to the next submission, and to a response when it is edited.

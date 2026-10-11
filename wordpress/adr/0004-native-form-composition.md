# Native form composition for #204–#207

## Design target

Editors arrange field controls, text, actions and status layouts in Divi. The existing REST handlers retain payload names, required rules, CAPTCHA, mail, receipts and storage. Verification uses unpublished copies and intercepted integrations.

## Approach A. Native Divi Contact Form adapter

`bindDiviForm(form, endpoint)` intercepts native fields and posts the existing payload.

Caller: place a Divi Contact Form with Contact Field children, then choose its bioco endpoint.

This reuses native field controls, but Divi's own mail and CAPTCHA runtime competes with the bioco lifecycle. It also restricts grouping and wizard steps to one module's supported children.

## Approach B. Custom parent and child form modules

`renderForm(attrs, children)` wraps field/group/step children in a form.

Caller: insert a Form parent, then add dedicated Step and Field children.

The HTML tree is direct. Editors must learn another container hierarchy, and arbitrary ordinary Divi rows/columns depend on undocumented parent nesting behavior.

## Approach C. Ordinary Divi layout with associated controls

`renderFormPart(kind, values, mode)` renders one controller, field or action. `connectForm(scope)` associates controls with the controller's HTML form. `composeForm(type, values)` builds native Section/Row/Column trees without writing them.

Caller: place one controller in a native section marked `bioco-form-scope`, then add/move native field/action modules and ordinary text/heading/row/column modules. A native row marked `bioco-form-step` is a wizard step.

HTML form association permits fields in ordinary layout siblings. Native decoration controls style each field and action. Controls stay disabled until the existing submission runtime has installed its listener, so missing JavaScript remains inert.

## Tradeoffs and depth

C gives ordinary Divi layout freedom and keeps one transport implementation. It loses A's built-in contact field settings and B's direct form ancestry. Connecting a scope must reject mismatched field identities, duplicate scalar fields and missing required controls. Each scope owns its instance configuration; duplication must never associate fields with another copy. A backend field identity determines type and required semantics; editorial copy cannot weaken them.

## Chosen design

C. The functional module owns backend identities and submission state. Saved Divi elements own labels, help, grouping, order, steps, decoration and outcome content. Existing fixed renderers remain for recovery until replacements pass native save/reopen and migration checks.

## Implementation steps

1. Add the controller, field and action metadata and registration in a separate bundle.
2. Deliver contact composition and intercepted success/error/retry/isolation tests.
3. Reuse the same composition for the four other simple forms.
4. Remove the membership exact-six assumption and traverse native step rows.
5. Add explicit Divi outcome states and preserve existing message text before retiring presentation overrides.
6. Verify native save/reopen on private copies after the release owner stages the reviewed candidate.

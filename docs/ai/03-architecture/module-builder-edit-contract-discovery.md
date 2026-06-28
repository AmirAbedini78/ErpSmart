# Module Builder Edit Contract Discovery

A module builder must treat record navigation as a base contract:

- index route
- create route
- detail/view route
- edit route or native edit action
- authorization-aware visibility
- no accidental override of back/edit actions
- localized labels must resolve to strings, not objects

Before enabling a feature in a generated module, compare against native modules and register the discovered pattern in the builder capability matrix.

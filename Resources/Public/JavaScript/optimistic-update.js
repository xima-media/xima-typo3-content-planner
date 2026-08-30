/**
* Module: @content-planner/optimistic-update
*
* Generic optimistic-update pattern: apply a DOM change immediately, fire the
* async request in the background, then either reconcile the DOM with the
* authoritative result on success or roll back the optimistic change on
* failure. Shared by any feature that wants an instant UI response instead of
* waiting for a full reload (first used by status changes, CP-15; intended to
* be reused as-is by the comment composer and aggregated comment view).
*/
class OptimisticUpdate {
  /**
   * Tracks the most recent run per scope, so a slow request that has since been
   * superseded cannot write a stale snapshot back over a newer result. Entries
   * only exist while a request is in flight and are removed on completion.
   *
   * @type {Map<string, number>}
   */
  static #latestRun = new Map();

  /**
   * @param {Object} options
   * @param {string} [options.scope]
   *   Identifies what is being updated (e.g. `"pages:12"`). When two runs share
   *   a scope, only the newest one is allowed to reconcile or roll back the DOM;
   *   superseded runs still reject, but leave the DOM to the newer run. Omit for
   *   updates that cannot overlap.
   * @param {() => *} options.apply
   *   Applies the optimistic DOM change immediately and returns a snapshot
   *   value that `rollback` needs to undo it. Return value is opaque to
   *   OptimisticUpdate and passed through unchanged.
   * @param {() => Promise<*>} options.request
   *   Performs the actual request. Its resolved value is passed to `reconcile`.
   * @param {(result: *, snapshot: *) => void} [options.reconcile]
   *   Called with the request result on success, to reconcile the optimistic
   *   DOM state with the authoritative one (e.g. correct a guessed value).
   * @param {(snapshot: *, error: *) => void} options.rollback
   *   Called on failure to undo the optimistic change using the snapshot
   *   returned by `apply`.
   * @param {(error: *) => void} [options.onError]
   *   Called after rollback, e.g. to surface a user-visible error message.
   * @returns {Promise<*>} resolves with the request result, rejects with the error (after rollback ran)
   */
  static async run({apply, request, reconcile, rollback, onError, scope}) {
    const snapshot = apply();
    const generation = OptimisticUpdate.#startRun(scope);

    try {
      const result = await request();

      if (reconcile && OptimisticUpdate.#isCurrent(scope, generation)) {
        reconcile(result, snapshot);
      }

      return result;
    } catch (error) {
      if (OptimisticUpdate.#isCurrent(scope, generation)) {
        rollback(snapshot, error);

        if (onError) {
          onError(error);
        }
      }

      throw error;
    } finally {
      OptimisticUpdate.#finishRun(scope, generation);
    }
  }

  /**
   * @param {string|undefined} scope
   * @returns {number} the generation assigned to this run
   */
  static #startRun(scope) {
    if (!scope) {
      return 0;
    }

    const generation = (OptimisticUpdate.#latestRun.get(scope) ?? 0) + 1;
    OptimisticUpdate.#latestRun.set(scope, generation);

    return generation;
  }

  /**
   * @param {string|undefined} scope
   * @param {number} generation
   * @returns {boolean} false when a newer run for the same scope has started
   */
  static #isCurrent(scope, generation) {
    return !scope || OptimisticUpdate.#latestRun.get(scope) === generation;
  }

  /**
   * @param {string|undefined} scope
   * @param {number} generation
   */
  static #finishRun(scope, generation) {
    if (scope && OptimisticUpdate.#latestRun.get(scope) === generation) {
      OptimisticUpdate.#latestRun.delete(scope);
    }
  }
}

export default OptimisticUpdate;

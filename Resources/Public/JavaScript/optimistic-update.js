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
   * @param {Object} options
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
  static async run({apply, request, reconcile, rollback, onError}) {
    const snapshot = apply();

    try {
      const result = await request();

      if (reconcile) {
        reconcile(result, snapshot);
      }

      return result;
    } catch (error) {
      rollback(snapshot, error);

      if (onError) {
        onError(error);
      }

      throw error;
    }
  }
}

export default OptimisticUpdate;

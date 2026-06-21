export class HistoryStack {
    constructor(limit = 100) {
        this.limit = limit;
        this.past = [];
        this.future = [];
    }

    push(action) {
        this.past.push(action);

        if (this.past.length > this.limit) {
            this.past.shift();
        }

        this.future = [];
    }

    undo() {
        const action = this.past.pop();

        if (!action) {
            return null;
        }

        this.future.push(action);

        return action;
    }

    redo() {
        const action = this.future.pop();

        if (!action) {
            return null;
        }

        this.past.push(action);

        return action;
    }

    remove(rowId, field) {
        const matches = (action) => action.rowId === rowId && action.field === field;

        this.past = this.past.filter((action) => !matches(action));
        this.future = this.future.filter((action) => !matches(action));
    }

    clear() {
        this.past = [];
        this.future = [];
    }

    canUndo() {
        return this.past.length > 0;
    }

    canRedo() {
        return this.future.length > 0;
    }
}

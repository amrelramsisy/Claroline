import {connect} from 'react-redux'
import merge from 'lodash/merge'

import {withReducer} from '#/main/app/store/components/withReducer'

import {
  actions as formActions,
  selectors as formSelect
} from '#/main/app/content/form/store'

import {Event as EventTypes} from '#/plugin/agenda/event/prop-types'
import {ParametersModal as ParametersModalComponent} from '#/plugin/agenda/event/modals/parameters/components/modal'
import {reducer, selectors} from '#/plugin/agenda/event/modals/parameters/store'

const ParametersModal = withReducer(selectors.STORE_NAME, reducer)(
  connect(
    (state) => ({
      saveEnabled: formSelect.saveEnabled(formSelect.form(state, selectors.STORE_NAME))
    }),
    (dispatch) => ({
      update(prop, value) {
        dispatch(formActions.updateProp(selectors.STORE_NAME, prop, value))
      },
      save(event) {
        if (event.id) {
          dispatch(formActions.saveForm(selectors.STORE_NAME, ['apiv2_event_update', {id: event.id}]))
        } else {
          dispatch(formActions.saveForm(selectors.STORE_NAME, ['apiv2_event_create']))
        }

      },
      loadEvent(event) {
        dispatch(formActions.resetForm(selectors.STORE_NAME, merge({}, event, EventTypes.defaultProps), !event.id))
      }
    })
  )(ParametersModalComponent)
)

export {
  ParametersModal
}
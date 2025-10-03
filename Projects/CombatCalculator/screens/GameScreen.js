import React, { useReducer, useState } from 'react';
import { View, Text, Pressable, StyleSheet, TextInput, Image, FlatList } from 'react-native';
import StatLine from '../components/StatLine';

const initialState = {
  mode: 'allocation',
  str: 1,
  hp: 10,
  magic: 1,
  points: 10,
  playerHP: null,
  playerMagic: null,
  enemyHP: 20,
  combatLog: [],
  playerNextAttack: null,
  canUseMagic: true,
  enemyLiving: true,
};

function reducer(state, action) {
  switch (action.type) {
    // --- ALLOCATION ---
    case 'PLUS':
      if (state.points === 0) return state;
      return { ...state, [action.stat]: state[action.stat] + 1, points: state.points - 1 };
    case 'MINUS':
      const min = action.stat === 'hp' ? 10 : 1;
      if (state[action.stat] <= min) return state;
      return { ...state, [action.stat]: state[action.stat] - 1, points: state.points + 1 };

    case 'START_COMBAT':
      return {
        ...state,
        mode: 'combat',
        playerHP: state.hp,
        playerMagic: state.magic,
        enemyHP: 20,
        enemyLiving: true,
        combatLog: ['Death is looming... Brace yourself for battle!'],
      };

    // --- COMBAT ---
    case 'ATTACK': {
      if (state.playerHP <= 0 || !state.enemyLiving) return state;

      const currentAttack = state.playerNextAttack ?? state.str;
      const damageToEnemy = currentAttack;
      const damageToPlayer = 4;

      const newEnemyHP = Math.max(state.enemyHP - damageToEnemy, 0);
      const newPlayerHP = Math.max(state.playerHP - damageToPlayer, 0);

      let newLog = [
        ...state.combatLog,
        `${action.name} hit Death for ${damageToEnemy} damage.`,
        `Death uses his scythe to strike you back for ${damageToPlayer} damage. Stay strong!`,
      ];

      if (newPlayerHP === 0 && newEnemyHP === 0) {
        newLog = [...newLog, 'Death has claimed your soul...you are now trapped in a fiery abyss forever.'];
      }
     else if (newEnemyHP === 0) {
        newLog = [...newLog, 'You struck Death down! Victory is yours! Now, escape!'];
      }
     else  if (newPlayerHP === 0) {
        newLog = [...newLog, 'Death has claimed your soul...you are now trapped in a fiery abyss forever.'];
      }


      return {
        ...state,
        enemyHP: newEnemyHP,
        playerHP: newPlayerHP,
        playerNextAttack: null,
        canUseMagic: true,
        enemyLiving: newEnemyHP > 0,
        combatLog: newLog,
      };
    }

    case 'MAGIC': {
      if (state.playerMagic <= 0 || state.playerHP <= 0 || state.enemyHP <= 0) {
        return {
          ...state,
          combatLog: [...state.combatLog, 'You have no magic left!'],
          canUseMagic: false,
          playerMagic: 0,
        };
      }

      let newLog = [...state.combatLog];
      let newPlayerHP = state.playerHP;
      let playerNextAttack = null;

      if (Math.random() < 0.5) {
        newPlayerHP = Math.min(state.playerHP + 1, state.hp);
        newLog.push('Magic healed you for 1 HP!');
      } else {
        playerNextAttack = state.str + 1;
        newLog.push('Magic increased your attack for this turn!');
      }

      return {
        ...state,
        playerMagic: state.playerMagic - 1,
        playerHP: newPlayerHP,
        playerNextAttack,
        canUseMagic: false,
        combatLog: newLog,
      };
    }

    case 'ESCAPE': {
      if (state.enemyHP === 0) {
        return { ...state, mode: 'allocation' };
        }
      return {
        ...state,
        combatLog: [...state.combatLog, 'You cannot escape while Death still lives!'],
      };
    }

    default:
      return state;
  }
}

export default function GameScreen({ navigation }) {
  const [state, dispatch] = useReducer(reducer, initialState);
  const [playerName, setPlayerName] = useState('');

  let battleTitle = "Battle Time!";
  if (state.playerHP <= 0) battleTitle = "GAME OVER!";
  else if (!state.enemyLiving) battleTitle = "Victory!";

  let imageSource;

if (state.playerHP <= 0) {
  imageSource = require('../assets/GAMEOVER.jpg');
} else if (!state.enemyLiving) {
  imageSource = require('../assets/DeathDefeated.png');
} else {
  imageSource = require('../assets/DeathEnemy.png');
}

  return (
    <View style={styles.container}>
      {state.mode === 'allocation' ? (
        <>
          <Text style={styles.title}>Make Your Hero</Text>
          <TextInput
            placeholder="Enter your hero's name"
            style={styles.input}
            value={playerName}
            onChangeText={setPlayerName}
          />
          <Text style={styles.points}>Points left: {state.points}</Text>

          <StatLine label="Strength" val={state.str} keyName="str"
            onPlus={(stat) => dispatch({ type: 'PLUS', stat })}
            onMinus={(stat) => dispatch({ type: 'MINUS', stat })}
          />
          <StatLine label="Health" val={state.hp} keyName="hp"
            onPlus={(stat) => dispatch({ type: 'PLUS', stat })}
            onMinus={(stat) => dispatch({ type: 'MINUS', stat })}
          />
          <StatLine label="Magic" val={state.magic} keyName="magic"
            onPlus={(stat) => dispatch({ type: 'PLUS', stat })}
            onMinus={(stat) => dispatch({ type: 'MINUS', stat })}
          />

          {state.points === 0 ? (
            <Pressable
              style={styles.startBtn}
              onPress={() => dispatch({ type: 'START_COMBAT' })}
            >
              <Text style={styles.btnText}>Go Fight</Text>
            </Pressable>
          ) : (
            <Text style={styles.message}>Finish allocating points first!</Text>
          )}
        </>
      ) : (
        <>
          <Text style={styles.title}>{battleTitle}</Text>

          <Image style={styles.imageStyle} source={imageSource} />


          <View style={styles.table}>
            <View style={styles.row}>
              <Text style={styles.cellHeader}>Stat</Text>
              <Text style={styles.cellHeader}>{playerName || 'Hero'}</Text>
              <Text style={styles.cellHeader}>Death</Text>
            </View>
            <View style={styles.row}>
              <Text style={styles.cell}>HP</Text>
              <Text style={styles.cell}>{state.playerHP}</Text>
              <Text style={styles.cell}>{state.enemyHP}</Text>
            </View>
            <View style={styles.row}>
              <Text style={styles.cell}>Strength</Text>
              <Text style={styles.cell}>{state.str}</Text>
              <Text style={styles.cell}>4</Text>
            </View>
            <View style={styles.row}>
              <Text style={styles.cell}>Magic</Text>
              <Text style={styles.cell}>{state.playerMagic}</Text>
              <Text style={styles.cell}>N/A</Text>
            </View>
          </View>

          <FlatList
            data={state.combatLog}
            keyExtractor={(item, index) => index.toString()}
            renderItem={({ item }) => <Text style={styles.combatText}>{item}</Text>}
          />

       
<View style={styles.actionRow}>
  {state.playerHP > 0 && state.enemyLiving && (
    <Pressable
      onPress={() => dispatch({ type: 'ATTACK', name: playerName || 'Hero' })}
    >
      <Image source={require('../assets/sword.png')} style={styles.iconStyle} />
    </Pressable>
  )}

  {state.canUseMagic && state.playerHP > 0 && state.enemyLiving && (
    <Pressable
      onPress={() => dispatch({ type: 'MAGIC' })}
    >
      <Image source={require('../assets/magic.png')} style={styles.iconStyle} />
    </Pressable>
  )}
</View>

          {state.playerHP >= 0 && !state.enemyLiving && (
            <Pressable
              style={styles.esapeButton}
              onPress={() => dispatch({ type: 'ESCAPE' })}
            >
              <Text style={styles.moveButtonText}>Escape</Text>
            </Pressable>
          )}

          {state.playerHP === 0 && state.enemyLiving && (
            <Pressable
              style={styles.esapeButton}
              onPress={() => navigation.navigate('Welcome')}
            >
              <Text style={styles.moveButtonText}>Return to Main Menu</Text>
            </Pressable>
          )}
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container:
  {
    flex: 1,
    backgroundColor: '#2a2a36',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20
},
  title:
  {
    fontSize: 32,
    fontWeight: '700',
    color: '#7bdff2',
    marginBottom: 20
},
  points:
  {
    fontSize: 16,
    color: '#fff',
    marginBottom: 20
},
  startBtn:
  {
    backgroundColor: '#000',
    paddingVertical: 10,
    paddingHorizontal: 25,
    borderRadius: 8,
    marginTop: 25
},
  btnText:
  { color: '#fff',
    fontSize: 18,
    fontWeight: '600'
},
  message:
  {
    marginTop: 20,
    color: '#fff',
    fontSize: 14
},
  input:
  {
    backgroundColor: '#fff',
    width: '60%',
    padding: 10,
    borderRadius: 8,
    marginBottom: 20,
    fontSize: 16,
    textAlign: 'center'
},
  imageStyle:
  { width: 200,
    height: 200,
    marginBottom: 20,
    resizeMode: 'contain'
},
  table:
  {
    borderWidth: 2,
    borderColor: '#fff',
    marginBottom: 20,
    width: '100%',
    alignSelf: 'center'
},
  row:
  {
    flexDirection: 'row',
    justifyContent: 'space-between',
    width: '100%'
},
  cellHeader:
  {
    flex: 1,
    padding: 8,
    color: '#fff',
    fontWeight: 'bold',
    textAlign: 'center',
    borderWidth: 1,
    borderColor: '#fff'
},
  cell:
  {
    flex: 1,
    padding: 10,
    color: '#fff',
    textAlign: 'center',
    borderColor: '#fff'
},
  combatText:
  {
    fontSize: 17,
    color: '#fff',
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 4
},
  attackButton:
  {
    backgroundColor: '#93deff',
    padding: 10,
    borderRadius: 9,
    marginTop: 20
},
  magicButton:
  {
    backgroundColor: 'purple',
    padding: 10,
    borderRadius: 9,
    marginTop: 20
},
  moveButtonText:
  { color: '#fff',
    fontSize: 20,
    fontWeight: '600'
},
  esapeButton:
  { backgroundColor: 'black',
     padding: 12,
     borderRadius: 10,
     marginTop: 20
    },

    iconStyle: {
        width: 50,
        height: 50,
        marginTop: 10,
        marginHorizontal: 20,        
    },
    actionRow: {
        flexDirection: 'row',
        justifyContent: 'space-around',
        width: '60%', 
        marginTop: 20,
      },
      
});

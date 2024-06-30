## General
AzusaMath is a Pocketmine plug-in that functions to provide maths problems and players can get prizes if they answer correctly.

## Features
- Consists of addition, subtraction, multiplication, and division
- Custom message
- Custom prize
- Custom economy
- Added a sound when a question appears, answers the question, and no one answers the question.

## Command
Commands | Default | Permission
--- | --- | ---
`/math` | Op | azusamath.command

## Configuration
```yaml
# Configuration for AzusaMath plugin

number_max: 100

# Time interval for maths questions to be answered
math_interval: 60

# Message when the player successfully answers the maths question
maths_completion_message: "§aCongratulations! §e{player} §asuccessfully answered the maths question correctly §6{money} §aMoney"

# Message when no one answers the maths question
no_answer_message: "§cNo one answered the maths question, move on to the next maths question..."

# Delay when question is missed or answered
maths_delay_solved: 5

# Rewards when players successfully answer maths questions
prize_min: 1000
prize_max: 10000

# Economy you are using (Must use LibPiggyEconomy)
Economy:
  type: "bedrockeconomy" # Change this to your specific economy provider if needed (bedrockeconomy/economyapi)
```

## Depend
| Authors | Github | Lib |
|---------|--------|-----|
| Cooldogepm | [Cooldogepm](https://github.com/cooldogepm) | [BedrockEconomy](https://github.com/cooldogepm/BedrockEconomy) |
| Mathchat900 | [Mathchat900](https://github.com/mathchat900) | [EconomyAPI](https://github.com/mathchat900/EconomyAPI-PM5) |
